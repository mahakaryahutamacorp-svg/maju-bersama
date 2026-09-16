<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        ChartOfAccount::create(['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense']);

        $this->branch = Branch::create([
            'name' => 'Gudang Pusat',
            'code' => 'PUSAT',
            'address' => 'Jl. Pusat Niaga No. 10',
            'phone' => '08123456789',
        ]);

        $this->user = User::create([
            'name' => 'Admin Stock Opname',
            'email' => 'opname@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Kebutuhan Pokok',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/inventory/adjustments');
        $response->assertRedirect('/login');

        $createResponse = $this->get('/inventory/adjustments/create');
        $createResponse->assertRedirect('/login');
    }

    public function test_user_can_view_index_and_create_pages(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-OPN-01',
            'name' => 'Kopi Robusta 250g',
            'selling_price' => 30000,
            'purchase_price' => 22000,
            'stock' => 15,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        $response = $this->actingAs($this->user)->get('/inventory/adjustments');
        $response->assertStatus(200);
        $response->assertSee('Riwayat Penyesuaian Stok (Opname)');

        $createResponse = $this->actingAs($this->user)->get('/inventory/adjustments/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Formulir Stock Opname');
        $createResponse->assertSee('Kopi Robusta 250g');
    }

    public function test_user_can_submit_opname_and_view_slip_with_journal(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-OPN-02',
            'name' => 'Gula Pasir 1kg',
            'selling_price' => 18000,
            'purchase_price' => 14000,
            'stock' => 20,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        // Kirim hasil opname fisik: fisik 18 karung (selisih -2, rugi 28.000)
        $postData = [
            'branch_id' => $this->branch->id,
            'date' => '2026-09-16',
            'notes' => 'Audit Gula Pasir: 2 karung sobek',
            'items' => [
                [
                    'product_id' => $product->id,
                    'expected_qty' => 20,
                    'actual_qty' => 18,
                    'unit_cost' => 14000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post('/inventory/adjustments', $postData);

        $adjustment = StockAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertEquals('28000.00', $adjustment->total_loss_value);

        $response->assertRedirect("/inventory/adjustments/{$adjustment->id}");
        $response->assertSessionHas('success');

        // Buka halaman show slip
        $showResponse = $this->actingAs($this->user)->get("/inventory/adjustments/{$adjustment->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee($adjustment->reference_number);
        $showResponse->assertSee('Gula Pasir 1kg');
        $showResponse->assertSee('28.000');
        $showResponse->assertSee('5120'); // Beban Selisih
        $showResponse->assertSee('1210'); // Persediaan
    }

    public function test_store_validation_fails_without_items(): void
    {
        $response = $this->actingAs($this->user)->post('/inventory/adjustments', [
            'branch_id' => $this->branch->id,
            'date' => '2026-09-16',
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
    }
}
