<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PosCheckoutTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Skenario E2E Browser: Kasir login, tambah produk ke cart, pilih pelanggan,
     * selesaikan pembayaran tunai, dan verifikasi receipt serta pemotongan stok di database.
     */
    public function test_cashier_can_checkout_product_via_pos_screen(): void
    {
        // 1. Siapkan Akun Akuntansi Wajib untuk Posting Jurnal POS
        ChartOfAccount::firstOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::firstOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        ChartOfAccount::firstOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);

        // 2. Siapkan Data Cabang
        $branch = Branch::create([
            'name' => 'Cabang Test POS',
            'code' => 'CAB-TEST-01',
            'address' => 'Jl. Uji Coba No. 123',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        // 3. Siapkan Mesin Kasir & User Kasir
        $register = CashRegister::create([
            'branch_id' => $branch->id,
            'name' => 'Mesin Kasir Utama',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Kasir Checkout Test',
            'email' => 'kasir.checkout@test.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Buka shift kasir aktif agar tidak terhalang modal pembuka shift
        CashRegisterShift::create([
            'branch_id' => $branch->id,
            'cash_register_id' => $register->id,
            'user_id' => $cashier->id,
            'opened_at' => now(),
            'opening_balance' => 100000,
            'status' => 'open',
        ]);

        // 4. Siapkan Data Pelanggan
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Pak Budi Pelanggan',
            'phone' => '081298765432',
        ]);

        // 5. Siapkan Kategori & Data Produk (Stok Awal = 10, Harga Valid)
        $category = Category::create([
            'name' => 'Pupuk Organik',
        ]);

        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Pupuk NPK Hayati',
            'sku' => 'NPK-HAYATI-01',
            'purchase_price' => 15000,
            'selling_price' => 25000,
            'stock' => 10,
        ]);

        Inventory::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // 6. Jalankan Skenario Browser E2E
        $this->browse(function (Browser $browser) use ($cashier, $customer, $product) {
            // A. Login Kasir
            $browser->visit('/login')
                ->type('email', $cashier->email)
                ->type('password', 'password')
                ->press('Masuk');

            // B. Akses Layar POS & Audit Layout Elemen Utama
            $browser->visit('/pos')
                ->waitFor('#btn-product-'.$product->id, 5)
                ->assertPresent('header')
                ->assertSee('Kasir POS Multi-Store')
                ->assertPresent('main')
                ->assertPresent('aside')
                ->assertPresent('#pos-customer-select')
                ->assertPresent('#btn-open-payment')
                ->assertSee($product->name);

            // C. Simulasikan klik pada produk dummy agar masuk ke keranjang belanja
            $browser->click('#btn-product-'.$product->id)
                ->pause(500)
                ->assertSeeIn('aside', $product->name);

            // D. Simulasikan pemilihan pelanggan dari dropdown pelanggan
            $browser->select('#pos-customer-select', (string) $customer->id)
                ->pause(800);

            // E. Klik tombol "Bayar" (Pembayaran F9) untuk memunculkan modal pembayaran
            $browser->waitUntilEnabled('#btn-open-payment', 10)
                ->pause(300)
                ->click('#btn-open-payment')
                ->waitFor('#input-cash-tendered', 10);

            // F. Isi input nominal uang (menggunakan nominal tunai / uang pas)
            $browser->type('#input-cash-tendered', '50000')
                ->pause(300);

            // G. Klik tombol konfirmasi pembayaran ("Selesaikan & Cetak Faktur")
            $browser->click('#btn-process-checkout');

            // H. Tunggu dan pastikan teks sukses atau elemen modal struk (Receipt) muncul di layar
            $browser->waitFor('#modal-receipt', 10)
                ->assertSee('Transaksi Berhasil!')
                ->pause(500);
        });

        // 7. Verifikasi Database (Assertion)
        // Pastikan stok produk di database kini berkurang dari 10 menjadi 9
        $this->assertEquals(9, $product->fresh()->stock);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 9,
        ]);

        // Pastikan data transaksi tersimpan di tabel sales
        $this->assertDatabaseHas('sales', [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $cashier->id,
            'status' => 'completed',
        ]);
    }
}
