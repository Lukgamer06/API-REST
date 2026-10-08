<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_register_validates_required_and_unique_fields(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'ada@example.com',
            'password' => 'incorrect-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Las credenciales proporcionadas no son válidas.');
    }

    public function test_bearer_token_authenticates_post_creation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('postman')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/posts', [
                'title' => 'Publicación autenticada',
                'content' => 'Contenido de prueba',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('posts', [
            'title' => 'Publicación autenticada',
            'user_id' => $user->id,
        ]);
    }

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/v1/logout')->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }
}
