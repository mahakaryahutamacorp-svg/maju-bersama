<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

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
        ])->assertRedirect('/pos');

        $this->assertAuthenticatedAs($user);
        $this->get('/inventory')->assertOk();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}