<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function loginAs(array $overrides = []): array
    {
        $user = User::factory()->create(array_merge([
            'email'    => 'khalil@fleet.com',
            'password' => bcrypt('password123'),
        ], $overrides));

        $token = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ])->json('token');

        return compact('user', 'token');
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function test_logout_returns_200_for_authenticated_user(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->postJson('/api/logout', [], $this->authHeader($token))
             ->assertStatus(200)
             ->assertJsonPath('message', 'Logged out');
    }

    public function test_logout_invalidates_the_used_token(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->postJson('/api/logout', [], $this->authHeader($token))
             ->assertStatus(200);

        // Same token must be rejected on subsequent requests
        $this->getJson('/api/user', $this->authHeader($token))
             ->assertStatus(401);
    }

    public function test_logout_removes_token_from_database(): void
    {
        ['token' => $token, 'user' => $user] = $this->loginAs();

        $this->assertSame(1, $user->tokens()->count(), 'One token before logout');

        $this->postJson('/api/logout', [], $this->authHeader($token))
             ->assertStatus(200);

        $this->assertSame(0, $user->tokens()->count(), 'Token must be deleted after logout');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')
             ->assertStatus(401);
    }

    public function test_unauthenticated_logout_attempt_returns_401(): void
    {
        // A request with no Authorization header must be rejected (no session or token)
        $this->postJson('/api/logout', [], [])
             ->assertStatus(401);
    }

    // ─── GET /api/user ────────────────────────────────────────────────────────

    public function test_get_user_returns_authenticated_users_data(): void
    {
        ['token' => $token] = $this->loginAs(['email' => 'khalil@fleet.com']);

        $this->getJson('/api/user', $this->authHeader($token))
             ->assertStatus(200)
             ->assertJsonPath('email', 'khalil@fleet.com');
    }

    public function test_get_user_returns_full_profile_fields(): void
    {
        ['token' => $token] = $this->loginAs();

        $response = $this->getJson('/api/user', $this->authHeader($token));

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'name', 'email', 'role', 'photo_url']);
    }

    public function test_get_user_fails_401_without_token(): void
    {
        $this->getJson('/api/user')
             ->assertStatus(401);
    }

    public function test_get_user_fails_401_with_invalid_token(): void
    {
        $this->getJson('/api/user', $this->authHeader('invalid-token-xyz'))
             ->assertStatus(401);
    }

    public function test_get_user_does_not_expose_password_hash(): void
    {
        ['token' => $token] = $this->loginAs();

        $response = $this->getJson('/api/user', $this->authHeader($token))
                         ->assertStatus(200);

        $this->assertArrayNotHasKey('password', $response->json());
    }

    // ─── Profile update (PUT /api/user/profile) ───────────────────────────────

    public function test_update_profile_changes_name_and_phone(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->putJson('/api/user/profile', [
            'name'  => 'Khalil Updated',
            'phone' => '+213555123456',
        ], $this->authHeader($token))
        ->assertStatus(200)
        ->assertJsonPath('user.name', 'Khalil Updated');
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/user/profile', ['name' => 'Test'])
             ->assertStatus(401);
    }

    // ─── Password change (PUT /api/user/password) ────────────────────────────

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->putJson('/api/user/password', [
            'current_password'      => 'password123',
            'password'              => 'newPassword456!',
            'password_confirmation' => 'newPassword456!',
        ], $this->authHeader($token))
        ->assertStatus(200)
        ->assertJsonPath('message', 'Password changed successfully.');
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->putJson('/api/user/password', [
            'current_password'      => 'wrong_current',
            'password'              => 'newPassword456!',
            'password_confirmation' => 'newPassword456!',
        ], $this->authHeader($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_fails_when_new_passwords_do_not_match(): void
    {
        ['token' => $token] = $this->loginAs();

        $this->putJson('/api/user/password', [
            'current_password'      => 'password123',
            'password'              => 'newPassword456!',
            'password_confirmation' => 'different_password',
        ], $this->authHeader($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->putJson('/api/user/password', [
            'current_password'      => 'password123',
            'password'              => 'newPassword456!',
            'password_confirmation' => 'newPassword456!',
        ])->assertStatus(401);
    }
}
