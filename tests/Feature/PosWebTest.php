<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $cashier;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Toko Cabang Utama',
            'code' => 'CAB-UTAMA',
            'address' => 'Jl. Protokol No. 88',
            'phone' => '081122334455',
        ]);

        $this->cashier = User::create([
            'name' => 'Siti Kasir',
            'email' => 'siti@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Makanan Ringan',
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-SNACK-01',
            'name' => 'Keripik Singkong Balado',
            'selling_price' => 15000,
            'purchase_price' => 10000,
            'stock' => 25,
        ]);
    }

    public function test_guest_cannot_access_pos_screen(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
    }

    public function test_cashier_can_open_pos_screen_with_categories_and_products(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Kasir POS Multi-Store');
        $response->assertSee('Toko Cabang Utama');
        $response->assertSee('Makanan Ringan');
        $response->assertSee('Keripik Singkong Balado');
        $response->assertSee('Scan Barcode');
    }

    public function test_cashier_can_view_thermal_receipt(): void
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-20260916-0099',
            'total_amount' => 3000000, // 30.000 (in cents)
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 1500000,
            'subtotal' => 3000000,
        ]);

        $response = $this->actingAs($this->cashier)->get("/pos/receipt/{$sale->receipt_number}?cash=50000&change=20000");

        $response->assertStatus(200);
        $response->assertSee('MAJU BERSAMA');
        $response->assertSee('Toko Cabang Utama');
        $response->assertSee('Siti Kasir');
        $response->assertSee('INV-20260916-0099');
        $response->assertSee('Keripik Singkong Balado');
        $response->assertSee('30.000');
        $response->assertSee('50.000');
        $response->assertSee('20.000');
    }
}
