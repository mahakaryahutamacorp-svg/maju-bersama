<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferPrintTest extends TestCase
{
    use RefreshDatabase;

    private Branch $originBranch;
    private Branch $destinationBranch;
    private User $user;
    private Product $product;
    private StockTransfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originBranch = Branch::create([
            'name'    => 'Gudang Pusat Jakarta',
            'code'    => 'WH-PST',
            'address' => 'Jl. Hayam Wuruk No. 88, Jakarta Barat',
            'phone'   => '08123456789',
        ]);

        $this->destinationBranch = Branch::create([
            'name'    => 'Cabang Bandung',
            'code'    => 'BDG-01',
            'address' => 'Jl. Asia Afrika No. 12, Bandung',
            'phone'   => '08987654321',
        ]);

        $this->user = User::create([
            'name'      => 'Budi Logistik',
            'email'     => 'budi@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'central_admin',
            'branch_id' => $this->originBranch->id,
        ]);

        $category = Category::create([
            'name' => 'Gadget & Smartphone',
            'slug' => 'gadget-smartphone',
        ]);

        $this->product = Product::create([
            'name'           => 'Xiaomi Redmi Note 13 Pro 8/256GB',
            'sku'            => 'XIA-RN13P-BLK',
            'category_id'    => $category->id,
            'branch_id'      => $this->originBranch->id,
            'unit'           => 'unit',
            'purchase_price' => 3200000,
            'selling_price'  => 3799000,
            'stock'          => 50,
        ]);

        $this->transfer = StockTransfer::create([
            'reference_number'      => 'TRF-202609-001',
            'source_branch_id'      => $this->originBranch->id,
            'destination_branch_id' => $this->destinationBranch->id,
            'created_by'            => $this->user->id,
            'transfer_date'         => now(),
            'status'                => 'completed',
            'notes'                 => 'Pengiriman mendesak untuk promo weekend',
        ]);

        StockTransferItem::create([
            'stock_transfer_id'      => $this->transfer->id,
            'source_product_id'      => $this->product->id,
            'destination_product_id' => $this->product->id,
            'quantity'               => 15,
            'unit_cost'              => 3200000,
        ]);
    }

    public function test_guest_cannot_access_stock_transfer_print(): void
    {
        $response = $this->get(route('stock-transfers.print', $this->transfer->reference_number));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_physical_delivery_note(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('stock-transfers.print', $this->transfer->reference_number));

        $response->assertOk();

        // 1. Kop Surat & Judul & Profil Perusahaan
        $response->assertSee('SURAT JALAN / DELIVERY NOTE');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Jl. Nusa Indah Ujung BK 9, OKU Timur, Belitang, Sumatera Selatan');
        $response->assertSee('085198548662');

        // 2. CSS Print 9x5 Continuous Form
        $response->assertSee('size: 9in 5in');
        $response->assertSee('margin: 0.1in 0.2in');

        // 3. Header Dokumen (Metadata)
        $response->assertSee('TRF-202609-001');
        $response->assertSee('Gudang Pusat Jakarta');
        $response->assertSee('Cabang Bandung');
        $response->assertSee('Budi Logistik');
        $response->assertSee('Pengiriman mendesak untuk promo weekend');

        // 4. Tabel Barang (No, Nama Barang, SKU, Qty)
        $response->assertSee('Xiaomi Redmi Note 13 Pro 8/256GB');
        $response->assertSee('XIA-RN13P-BLK');
        $response->assertSee('15');

        // 5. CRITICAL: Tidak menampilkan harga modal / unit cost / HPP
        $response->assertDontSee('3.200.000');
        $response->assertDontSee('3200000');
        $response->assertDontSee('Harga Modal');
        $response->assertDontSee('HPP');

        // 6. Footer Tanda Tangan 3 Kolom
        $response->assertSee('Dikeluarkan Oleh (Gudang Asal)');
        $response->assertSee('Dibawa Oleh (Kurir)');
        $response->assertSee('Diterima Oleh (Cabang Tujuan)');

        // 7. Footer Identitas Aplikasi
        $response->assertSee('maju bersama abi - 2029 | supported by rocellgadget');

        // 8. Script Cetak Otomatis
        $response->assertSee('window.print()');
    }

    public function test_modal_viewer_has_button_to_print_surat_jalan(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', $this->transfer->reference_number));

        $response->assertOk();
        $response->assertSee('Cetak Surat Jalan');
        $response->assertSee(route('stock-transfers.print', $this->transfer->reference_number));
        $response->assertSee('target="_blank"', false);
    }

    public function test_print_returns_404_for_unknown_reference(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('stock-transfers.print', 'NON-EXISTENT-REF'));

        $response->assertNotFound();
    }
}
