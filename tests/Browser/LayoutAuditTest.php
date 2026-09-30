<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LayoutAuditTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Audit halaman publik dan login untuk memastikan elemen layout dirender sempurna.
     */
    public function test_public_and_login_pages_render_layout(): void
    {
        $this->browse(function (Browser $browser) {
            // 1. Visit Public Preview
            $browser->visit('/')
                ->assertPresent('header')
                ->assertSee('Maju Bersama ERP')
                ->assertPresent('main')
                ->assertPresent('table');

            // 2. Visit Login Screen
            $browser->visit('/login')
                ->assertPresent('form')
                ->assertPresent('input#email')
                ->assertPresent('input#password')
                ->assertSee('Akses Sistem');
        });
    }

    /**
     * Audit rute-rute utama aplikasi pada state terotentikasi.
     */
    public function test_authenticated_core_routes_render_layout(): void
    {
        $branch = Branch::create([
            'name' => 'Cabang Audit Master',
            'code' => 'AUD-MST-01',
            'address' => 'Jl. Audit Pusat No. 1',
            'phone' => '08111222333',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Master Audit Admin',
            'email' => 'admin.audit@test.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'is_active' => true,
        ]);

        $register = CashRegister::create([
            'branch_id' => $branch->id,
            'name' => 'Register Audit',
            'is_active' => true,
        ]);

        CashRegisterShift::create([
            'branch_id' => $branch->id,
            'cash_register_id' => $register->id,
            'user_id' => $admin->id,
            'opened_at' => now(),
            'opening_balance' => 50000,
            'status' => 'open',
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            // Login
            $browser->visit('/login')
                ->type('email', $admin->email)
                ->type('password', 'password')
                ->press('Masuk');

            // 1. Audit Dashboard Backoffice
            $browser->visit('/backoffice')
                ->assertPresent('aside')
                ->assertPresent('header')
                ->assertPresent('main')
                ->assertSee('Backoffice')
                ->assertSee('Master access');

            // 2. Audit POS Screen
            $browser->visit('/pos')
                ->assertPresent('header')
                ->assertPresent('main')
                ->assertPresent('aside')
                ->assertSee('Kasir POS Multi-Store');

            // 3. Audit Inventory
            $browser->visit('/inventory')
                ->assertPresent('header')
                ->assertPresent('main')
                ->assertSee('Persediaan Barang');

            // 4. Audit Report Center
            $browser->visit('/backoffice/reports')
                ->assertPresent('header')
                ->assertSee('Pusat Laporan');

            // 5. Audit Journal Ledger
            $browser->visit('/reports/journal')
                ->assertPresent('header')
                ->assertPresent('main')
                ->assertSee('Jurnal');
        });
    }
}
