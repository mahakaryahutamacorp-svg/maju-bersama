<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test RBAC layout Backoffice (sidebar + overview).
 * Memastikan modul Enterprise & pengaturan master tidak bocor ke role cashier / branch_admin.
 */
class BackofficeRbacLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::create(['code' => 'PUSAT', 'name' => 'Cabang Pusat RBAC']);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['branch_id' => $this->branch->id, 'role' => $role]);
    }

    /** Teks yang HANYA boleh muncul untuk master. */
    private array $enterpriseMarkers = [
        '1. Pesan Barang (PO)',
        'Riwayat Bayar Hutang',
        'Retur ke Pemasok',
        'Cadangan Database Sistem (Manual Backup)',
        'Review ledger',
        'Buka ledger',
        'Manajemen Cabang',
        'Staf &amp; Kasir',
        'Kategori Biaya',
        'Buku Pemasok &amp; Hutang',
        'Jurnal Umum',
        'Saldo Awal (Setup)',
    ];

    public function test_cashier_only_sees_dashboard_and_kasir_division(): void
    {
        $response = $this->actingAs($this->makeUser('cashier'))->get('/backoffice')->assertOk();

        $response->assertSee('Beranda')
            ->assertSee('Kasir (POS)')
            ->assertSee('Shift Kasir')
            ->assertDontSee('Riwayat Penjualan')
            ->assertDontSee('Stok Gudang')
            ->assertDontSee('Belanja Barang')
            ->assertDontSee('Pengaturan</span>', false)
            ->assertDontSee('Katalog Produk')
            ->assertDontSee('Jualan</span>', false)
            ->assertDontSee('Uang &amp; Akuntansi', false)
            ->assertDontSee('Pusat Laporan')
            ->assertDontSee('Check stock');

        foreach ($this->enterpriseMarkers as $marker) {
            $response->assertDontSee($marker, false);
        }
    }

    public function test_unknown_role_is_treated_as_cashier(): void
    {
        $response = $this->actingAs($this->makeUser('kasir'))->get('/backoffice')->assertOk();

        $response->assertDontSee('Stok Gudang')
            ->assertDontSee('Uang &amp; Akuntansi', false);
    }

    public function test_branch_admin_sees_inventory_and_partial_master_data_only(): void
    {
        $response = $this->actingAs($this->makeUser('branch_admin'))->get('/backoffice')->assertOk();

        $response->assertSee('Beranda')
            ->assertSee('Riwayat Penjualan')
            ->assertSee('Stok Gudang')
            ->assertSee('2. Terima Barang')
            ->assertSee('Katalog Produk')
            ->assertSee('Data Pelanggan')
            ->assertSee('Piutang Pelanggan')
            ->assertSee('Biaya Operasional')
            ->assertSee('Ringkasan Keuangan')
            ->assertSee('Kas &amp; Bank', false)
            ->assertSee('Shift Kasir')
            ->assertSee('Pusat Laporan');

        foreach ($this->enterpriseMarkers as $marker) {
            $response->assertDontSee($marker, false);
        }
    }

    public function test_master_sees_all_divisions_backup_widget_and_ledger(): void
    {
        $response = $this->actingAs($this->makeUser('master'))->get('/backoffice')->assertOk();

        foreach ($this->enterpriseMarkers as $marker) {
            $response->assertSee($marker, false);
        }

        $response->assertSee('Stok Gudang')
            ->assertSee('Jualan</span>', false)
            ->assertSee('Uang &amp; Akuntansi', false)
            ->assertSee('Ringkasan Keuangan')
            ->assertSee('Pusat Laporan')
            ->assertDontSee('Shift Kasir');
    }

    public function test_non_master_cannot_hit_backup_endpoints_directly(): void
    {
        foreach (['cashier', 'branch_admin'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get('/backoffice/backup/download')->assertForbidden();
            $this->actingAs($user)->post('/backoffice/backup/generate')->assertForbidden();
        }
    }

    public function test_lihat_website_link_has_tooltip_and_does_not_truncate(): void
    {
        $this->actingAs($this->makeUser('cashier'))
            ->get('/backoffice')
            ->assertSee('id="btn-lihat-website"', false)
            ->assertSee('title="Lihat Website (tab baru)"', false)
            ->assertSee('whitespace-nowrap', false);
    }

    public function test_cashier_is_forbidden_from_sensitive_backend_routes(): void
    {
        $cashier = $this->makeUser('cashier');

        // Keuangan & Akuntansi (strictly master)
        $this->actingAs($cashier)->get('/reports/journal')->assertForbidden();
        $this->actingAs($cashier)->get('/reports/accounting/ledger')->assertForbidden();
        $this->actingAs($cashier)->get('/purchases/payables')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/supplier-payments')->assertForbidden();

        // Operasional Cabang & Gudang (branch_admin only)
        $this->actingAs($cashier)->get('/inventory')->assertForbidden();
        $this->actingAs($cashier)->get('/inventory/transfer')->assertForbidden();
        $this->actingAs($cashier)->get('/reports/sales')->assertForbidden();
        $this->actingAs($cashier)->get('/reports/ar-aging')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/expenses')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/finance')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/fixed-assets')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/purchase-orders')->assertForbidden();
        $this->actingAs($cashier)->get('/backoffice/payments')->assertForbidden();
    }

    public function test_branch_admin_is_forbidden_from_enterprise_accounting_and_ap(): void
    {
        $branchAdmin = $this->makeUser('branch_admin');

        // Keuangan & Akuntansi Enterprise / AP must be forbidden
        $this->actingAs($branchAdmin)->get('/reports/journal')->assertForbidden();
        $this->actingAs($branchAdmin)->get('/reports/accounting/ledger')->assertForbidden();
        $this->actingAs($branchAdmin)->get('/purchases/payables')->assertForbidden();
        $this->actingAs($branchAdmin)->get('/backoffice/reports')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/supplier-payments')->assertForbidden();

        // But branch operations for own branch MUST be allowed
        $this->actingAs($branchAdmin)->get('/inventory')->assertOk();
        $this->actingAs($branchAdmin)->get('/reports/sales')->assertOk();
        $this->actingAs($branchAdmin)->get('/reports/ar-aging')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/expenses')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/finance')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/customers')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/fixed-assets')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/purchase-orders')->assertOk();
        $this->actingAs($branchAdmin)->get('/backoffice/payments')->assertOk();
    }
}
