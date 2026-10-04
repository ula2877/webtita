<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'name' => 'Test User',
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_login_with_user_id(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/login', [
            'user_id' => $user->id,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role', 'is_active']]);
    }

    public function test_login_requires_selected_user_and_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'password'])
            ->assertJsonPath('errors.user_id.0', 'Silakan pilih nama.')
            ->assertJsonPath('errors.password.0', 'Silakan masukkan password.');
    }

    public function test_login_options_returns_active_users_with_role_and_status(): void
    {
        $this->makeUser(User::ROLE_ADMIN);
        $this->makeUser(User::ROLE_PETUGAS);
        User::create([
            'name' => 'Non Aktif',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/login-options');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Test User')
            ->assertJsonPath('data.0.role', User::ROLE_ADMIN)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/login', [
            'user_id' => $user->id,
            'password' => 'wrong',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_fails_with_unknown_user(): void
    {
        $this->postJson('/api/login', [
            'user_id' => 99999,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser();
        $user->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'user_id' => $user->id,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Test User');
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }
}