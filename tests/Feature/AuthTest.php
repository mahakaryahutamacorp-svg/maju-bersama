<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_login_and_receive_a_sanctum_token(): void
    {
        $branch = Branch::create([
            'code' => 'PUSAT',
            'name' => 'Pusat',
        ]);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user' => ['branch']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_user_can_logout_and_revoke_the_current_token(): void
    {
        $branch = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $token = $user->createToken('auth_token')->plainTextToken;
        $tokenId = $user->tokens()->latest('id')->value('id');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}