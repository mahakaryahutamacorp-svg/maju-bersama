<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use PreparesLedger;

    public function test_tc001_master_login_profile_and_logout(): void
    {
        [, $master] = $this->branchWithRoles();

        $login = $this->postJson('/api/login', [
            'email' => $master->email,
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.id', $master->id)
            ->assertJsonStructure(['token', 'user' => ['branch']]);

        $token = $login->json('token');

        auth('web')->logout();
        $this->flushSession();
        $this->defaultCookies = [];

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $master->id)
            ->assertJsonPath('branch.id', $master->branch_id);

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $master->id,
        ]);

        auth('web')->logout();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_tc001_wrong_password_is_rejected_and_guest_cannot_read_profile(): void
    {
        [, $master] = $this->branchWithRoles();

        // API menolak kredensial salah lewat validasi 422, bukan 401.
        // 401 dipakai saat token tidak ada.
        $this->postJson('/api/login', [
            'email' => $master->email,
            'password' => 'bukan-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->getJson('/api/user')->assertUnauthorized();

        $this->assertSame(0, User::where('email', $master->email)->first()->tokens()->count());
    }
}
