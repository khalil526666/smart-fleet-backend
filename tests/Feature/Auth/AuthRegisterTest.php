<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'name'                  => 'Test User',
        'email'                 => 'test@fleet.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    // ─── Success cases ────────────────────────────────────────────────────────

    public function test_register_creates_user_and_returns_201_with_token(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'token',
                     'token_type',
                     'user' => ['id', 'name', 'email', 'role'],
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'test@fleet.com']);
        $this->assertNotNull($response->json('token'));
    }

    public function test_register_always_assigns_role_user_regardless_of_input(): void
    {
        $this->postJson('/api/register', $this->validPayload)
             ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'test@fleet.com',
            'role'  => 'user',
        ]);
    }

    public function test_register_hashes_the_password_before_storing(): void
    {
        $this->postJson('/api/register', $this->validPayload);

        $user = User::where('email', 'test@fleet.com')->firstOrFail();

        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_register_returns_usable_bearer_token(): void
    {
        $token = $this->postJson('/api/register', $this->validPayload)
                      ->json('token');

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
             ->assertStatus(200);
    }

    public function test_register_response_does_not_expose_password_hash(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload);

        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_register_stores_correct_name_and_email(): void
    {
        $this->postJson('/api/register', $this->validPayload)
             ->assertStatus(201)
             ->assertJsonPath('user.name', 'Test User')
             ->assertJsonPath('user.email', 'test@fleet.com');
    }

    // ─── Validation — missing fields ─────────────────────────────────────────

    public function test_register_fails_422_without_name(): void
    {
        $this->postJson('/api/register', array_merge($this->validPayload, ['name' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name']);
    }

    public function test_register_fails_422_without_email(): void
    {
        $this->postJson('/api/register', array_merge($this->validPayload, ['email' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_422_with_malformed_email(): void
    {
        $this->postJson('/api/register', array_merge($this->validPayload, ['email' => 'not-an-email']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_422_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@fleet.com']);

        $this->postJson('/api/register', $this->validPayload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_422_with_short_password(): void
    {
        $this->postJson('/api/register', array_merge($this->validPayload, [
            'password'              => '123',
            'password_confirmation' => '123',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_422_when_passwords_do_not_match(): void
    {
        $this->postJson('/api/register', array_merge($this->validPayload, [
            'password_confirmation' => 'different_password',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_422_with_empty_body(): void
    {
        $this->postJson('/api/register', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_duplicate_registration_does_not_create_second_user(): void
    {
        $this->postJson('/api/register', $this->validPayload)->assertStatus(201);

        $this->postJson('/api/register', $this->validPayload)->assertStatus(422);

        $this->assertSame(1, User::where('email', 'test@fleet.com')->count());
    }
}
