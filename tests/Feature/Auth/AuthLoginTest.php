<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    // ─── Success cases ────────────────────────────────────────────────────────

    public function test_login_returns_token_and_user_with_valid_credentials(): void
    {
    // ARRANGE : créer un utilisateur de test avec des identifiants valides
    // et lui attribuer le rôle administrateur.
        User::factory()->create([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin',
        ]);
    // ACT : envoyer une requête de connexion avec les identifiants
    // précédemment enregistrés dans la base de données.
        $response = $this->postJson('/api/login', [
            'email'    => 'khalil@fleet.com',
            'password' => 'password123',
        ]);
    // ASSERT : vérifier que la connexion est réussie,
        // qu’un token d’authentification est généré
        // et que les informations de l’utilisateur sont retournées.
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'token_type',
                     'user' => ['id', 'name', 'email', 'role'],
                 ]);
        // ASSERT : vérifier que le token n'est ni nul ni vide.
        $this->assertNotNull($response->json('token'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_correct_role_in_user_payload(): void
    {
        User::factory()->gestionnaire()->create([
            'email'    => 'manager@fleet.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'manager@fleet.com',
            'password' => 'password123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('user.role', 'gestionnaire');
    }

    public function test_login_returns_correct_email_in_user_payload(): void
    {
        User::factory()->create([
            'email'    => 'driver@fleet.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'driver@fleet.com',
            'password' => 'password123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('user.email', 'driver@fleet.com');
    }

    public function test_issued_token_grants_access_to_protected_routes(): void
    {
        User::factory()->create([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/login', [
            'email'    => 'khalil@fleet.com',
            'password' => 'password123',
        ])->json('token');

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
             ->assertStatus(200);
    }

    public function test_login_invalidates_previous_tokens_on_success(): void
    {
        $user = User::factory()->create([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('password123'),
        ]);

        // First login — one token in DB
        $this->postJson('/api/login', [
            'email' => 'khalil@fleet.com', 'password' => 'password123',
        ])->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());

        // Second login — AuthController deletes ALL existing tokens, then issues a new one
        $this->postJson('/api/login', [
            'email' => 'khalil@fleet.com', 'password' => 'password123',
        ])->assertStatus(200);

        // Only the newly-issued token should remain (old one was purged)
        $this->assertSame(1, $user->tokens()->count());
    }

    // ─── Validation — wrong credentials ──────────────────────────────────────

    public function test_login_fails_422_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('correct_password'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'khalil@fleet.com',
            'password' => 'wrong_password',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_422_with_nonexistent_email(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'ghost@fleet.com',
            'password' => 'password123',
        ])
        ->assertStatus(422);
    }

    // ─── Validation — missing / malformed fields ──────────────────────────────

    public function test_login_fails_422_when_email_is_missing(): void
    {
        $this->postJson('/api/login', ['password' => 'password123'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_422_when_password_is_missing(): void
    {
        $this->postJson('/api/login', ['email' => 'khalil@fleet.com'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['password']);
    }

    public function test_login_fails_422_with_malformed_email(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'not-an-email',
            'password' => 'password123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_422_with_empty_body(): void
    {
        $this->postJson('/api/login', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_response_does_not_expose_password_hash(): void
    {
        User::factory()->create([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'khalil@fleet.com',
            'password' => 'password123',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}
