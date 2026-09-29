<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginAndPosTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Skenario pengujian login dan verifikasi halaman POS.
     */
    public function test_user_can_login_and_access_pos(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'TEST-01'],
            ['name' => 'Cabang Test']
        );

        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'kasir@test.com',
            'password' => bcrypt('password'),
            'role' => 'kasir',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Masuk');

            // Verifikasi perpindahan ke dashboard (backoffice pada sistem ini)
            if (str_contains($browser->driver->getCurrentURL(), '/backoffice')) {
                $browser->assertPathIs('/backoffice');
            } else {
                $browser->assertPathIs('/dashboard');
            }

            // Arahkan browser ke halaman /pos
            $browser->visit('/pos')
                ->pause(1000)
                ->assertSee('Total');
        });
    }
}
