<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    public function test_guest_can_open_login_and_is_redirected_from_pos(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/pos')->assertRedirect('/login');
    }

    public function test_branch_user_can_login_and_logout_from_web(): void
    {
        $branch = Branch::create(['code' => 'CABANG-1', 'name' => 'majubersama 1']);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'admin1@majubersama.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/backoffice');

        $this->assertAuthenticatedAs($user);
        $this->get('/inventory')->assertOk();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_master_user_can_monitor_all_branches(): void
    {
        $central = Branch::create(['code' => 'PUSAT', 'name' => 'majubersamapusat']);
        $child = Branch::create(['parent_id' => $central->id, 'code' => 'CABANG-1', 'name' => 'majubersama 1']);
        $master = User::factory()->create([
            'branch_id' => $central->id,
            'email' => 'master@majubersama.test',
            'password' => 'password',
            'role' => 'master',
        ]);

        $this->actingAs($master)
            ->get('/backoffice')
            ->assertOk()
            ->assertSee('All branches')
            ->assertSee('majubersama 1')
            ->assertSee('Master monitoring');

        $this->actingAs($master)
            ->get('/inventory')
            ->assertOk();
    }
}