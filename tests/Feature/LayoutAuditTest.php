<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $masterUser;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup COA
        ChartOfAccount::firstOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::firstOrCreate(['code' => '5100'], ['name' => 'HPP', 'type' => 'expense']);

        // 2. Setup Branch & Master User
        $this->branch = Branch::create([
            'name' => 'Cabang Audit Pusat',
            'code' => 'CAB-AUD-01',
            'address' => 'Jl. Audit No. 1',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $this->masterUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Auditor Master',
            'email' => 'master.audit@test.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'is_active' => true,
        ]);

        $register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Register Audit',
            'is_active' => true,
        ]);

        CashRegisterShift::create([
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'user_id' => $this->masterUser->id,
            'opened_at' => now(),
            'opening_balance' => 100000,
            'status' => 'open',
        ]);

        Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Gudang Audit',
            'code' => 'WH-AUD-01',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Kategori Audit']);

        Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'name' => 'Produk Audit 1',
            'sku' => 'SKU-AUD-01',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 20,
        ]);
    }

    /**
     * Audit rute publik (Preview & Login)
     */
    public function test_public_routes_respond_with_200_and_contain_vite_assets(): void
    {
        $publicRoutes = [
            '/' => 'Maju Bersama',
            '/login' => 'Masuk',
        ];

        foreach ($publicRoutes as $url => $expectedText) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $response->assertSee($expectedText);

            $html = $response->getContent();
            $this->assertStringContainsString('<!DOCTYPE html>', $html, "Missing DOCTYPE in {$url}");
            $this->assertStringContainsString('<head>', $html, "Missing <head> in {$url}");
            $this->assertTrue(
                str_contains($html, 'build/assets/') || str_contains($html, '.css'),
                "Missing compiled Vite CSS asset in {$url}"
            );
            $this->assertStringContainsString('min-h-screen', $html, "Missing min-h-screen container class in {$url}");
        }
    }

    /**
     * Audit seluruh rute utama Backoffice & Operasional
     */
    public function test_core_backoffice_and_operational_routes_respond_with_200_and_valid_layout(): void
    {
        $routes = [
            // Dashboard / Master
            '/backoffice' => 'Backoffice',
            '/dashboard' => 'Backoffice',
            '/backoffice/products' => 'Katalog Produk',
            '/backoffice/products/create' => 'Tambah Produk',
            '/backoffice/categories' => 'Kategori',
            '/backoffice/branches' => 'Manajemen Cabang',
            '/backoffice/branches/create' => 'Daftarkan Cabang',
            '/backoffice/users' => 'Staf & Kasir',
            '/backoffice/warehouses' => 'Multi Gudang',
            '/backoffice/suppliers' => 'Supplier',
            '/backoffice/customers' => 'Pelanggan',
            '/backoffice/customers/create' => 'Tambah Pelanggan',

            // Pengadaan & Transaksi
            '/backoffice/purchase-orders' => 'Pembelian / PO',
            '/backoffice/purchase-orders/create' => 'Buat Purchase Order',
            '/backoffice/purchase-returns' => 'Retur Pembelian',
            '/backoffice/sales-returns' => 'Retur Penjualan',
            '/backoffice/supplier-payments' => 'Pembayaran Supplier',
            '/backoffice/expense-categories' => 'Kategori Biaya',
            '/backoffice/expenses' => 'Biaya Operasional',
            '/backoffice/cash-transfers' => 'Mutasi Kas',
            '/backoffice/cash-transactions/create' => 'Kas Masuk',
            '/backoffice/fixed-assets' => 'Harta Tetap',
            '/backoffice/payments' => 'Pembayaran',
            '/backoffice/payments/receivables/create' => 'Piutang',
            '/backoffice/payments/payables/create' => 'Hutang',

            // Operasional Kasir & Inventory
            '/pos' => 'Kasir POS',
            '/inventory' => 'Persediaan',
            '/inventory/transfer' => 'Transfer Stok',
            '/inventory/adjustments' => 'Stock Opname',
            '/inventory/adjustments/create' => 'Stock Opname',
            '/purchases/goods-receipts' => 'Penerimaan Barang',

            // Laporan & Akuntansi
            '/backoffice/reports' => 'Pusat Laporan',
            '/reports/journal' => 'Jurnal',
            '/reports/accounting/ledger' => 'Buku Besar',
            '/reports/accounting/trial-balance' => 'Neraca Saldo',
            '/reports/accounting/income-statement' => 'Laba Rugi',
            '/reports/inventory/stock-card' => 'Kartu Stok',
            '/backoffice/reports/sales' => 'Penjualan',
            '/backoffice/reports/purchases' => 'Pembelian',
            '/backoffice/reports/balance-sheet' => 'Neraca',
            '/backoffice/reports/cash-flow' => 'Arus Kas',
            '/backoffice/reports/fixed-assets' => 'Aset Tetap',
            '/backoffice/reports/ar-aging' => 'Piutang',
            '/backoffice/reports/ap-aging' => 'Hutang',
        ];

        foreach ($routes as $url => $keyword) {
            $response = $this->actingAs($this->masterUser)->get($url);

            $this->assertEquals(
                200,
                $response->status(),
                "Route {$url} failed with status {$response->status()}."
            );

            $html = $response->getContent();

            // Verifikasi tag dasar HTML & Vite
            $this->assertStringContainsString('<!DOCTYPE html>', $html, "Route {$url} is missing <!DOCTYPE html>");
            $this->assertStringContainsString('<head>', $html, "Route {$url} is missing <head>");
            $this->assertTrue(
                str_contains($html, 'build/assets/') || str_contains($html, '.css'),
                "Route {$url} is missing Vite CSS bundle"
            );

            // Verifikasi tag pembungkus utama memiliki kelas layout kontainer
            $this->assertStringContainsString('min-h-screen', $html, "Route {$url} is missing min-h-screen container");
        }
    }
}
