<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class GoodsReceiptWebTest extends TestCase
{
    private Branch $central;
    private Branch $branchOne;
    private User $master;
    private User $branchCashier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $this->branchOne = Branch::create(['code' => 'CAB-1', 'name' => 'Cabang 1', 'parent_id' => $this->central->id]);

        $this->master = User::factory()->create(['branch_id' => $this->central->id, 'role' => 'master']);
        $this->branchCashier = User::factory()->create(['branch_id' => $this->branchOne->id, 'role' => 'cashier']);

        ChartOfAccount::firstOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::firstOrCreate(['code' => '2110'], ['name' => 'Hutang Dagang', 'type' => 'liability']);

        $category = Category::create(['name' => 'Makanan']);
        $this->product = Product::create([
            'branch_id' => $this->central->id,
            'category_id' => $category->id,
            'sku' => 'PRD-TEST-1',
            'name' => 'Beras Organik 5kg',
            'purchase_price' => 50000,
            'selling_price' => 65000,
            'stock' => 10,
        ]);
    }

    public function test_unauthorized_branch_user_cannot_access_goods_receipts(): void
    {
        $response = $this->actingAs($this->branchCashier)->get('/purchases/goods-receipts');
        $response->assertStatus(403);
    }

    public function test_master_can_view_index_and_create_page(): void
    {
        $indexResponse = $this->actingAs($this->master)->get('/purchases/goods-receipts');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Penerimaan Barang (Goods Receipt)');

        $createResponse = $this->actingAs($this->master)->get('/purchases/goods-receipts/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Formulir Goods Receipt');
        $createResponse->assertSee('Beras Organik 5kg');
        $createResponse->assertSee('goodsReceiptForm');
    }

    public function test_master_can_store_goods_receipt_and_view_show_slip(): void
    {
        $payload = [
            'supplier_name' => 'PT Pangan Makmur',
            'date' => now()->toDateString(),
            'payment_type' => 'cash',
            'reference_number' => 'GR-WEB-001',
            'notes' => 'Pengiriman perdana',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 25,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $postResponse = $this->actingAs($this->master)->post('/purchases/goods-receipts', $payload);

        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        // Follow redirect to show page
        $showUrl = $postResponse->headers->get('Location');
        $showResponse = $this->actingAs($this->master)->get($showUrl);

        $showResponse->assertStatus(200);
        $showResponse->assertSee('BUKTI PENERIMAAN BARANG');
        $showResponse->assertSee('GR-WEB-001');
        $showResponse->assertSee('PT Pangan Makmur');
        $showResponse->assertSee('Rp 1.250.000');
        $showResponse->assertSee('Jurnal Terposting');
    }

    public function test_master_can_pull_po_and_store_goods_receipt(): void
    {
        $supplier = \App\Models\Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'PT Pangan Sumber Utama',
            'is_active' => true,
        ]);

        $po = \App\Models\PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-TEST-WEB-01',
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 500000.00,
        ]);

        $poItem = \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_price' => 50000,
            'subtotal' => 500000,
        ]);

        // 1. Create page should see the active PO in JSON and in select options
        $createResponse = $this->actingAs($this->master)->get('/purchases/goods-receipts/create');
        $createResponse->assertOk();
        $createResponse->assertSee('Tarik Data dari Purchase Order');
        $createResponse->assertSee('PO-TEST-WEB-01');
        $createResponse->assertSee('loadFromPO');

        // 2. Submit Goods Receipt with purchase_order_id
        $payload = [
            'purchase_order_id' => $po->id,
            'supplier_name' => 'PT Pangan Sumber Utama',
            'date' => now()->toDateString(),
            'payment_type' => 'credit',
            'notes' => 'Penerimaan bertahap truk 1',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 6,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $postResponse = $this->actingAs($this->master)->post('/purchases/goods-receipts', $payload);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        // 3. Verify PO updated to partial
        $po->refresh();
        $this->assertEquals('partial', $po->status);
        $poItem->refresh();
        $this->assertEquals(6, $poItem->received_quantity);
    }
}
