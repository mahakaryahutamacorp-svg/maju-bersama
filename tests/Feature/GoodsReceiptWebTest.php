<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
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
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'PT Pangan Sumber Utama',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-TEST-WEB-01',
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 500000.00,
        ]);

        $poItem = PurchaseOrderItem::create([
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

    public function test_branch_admin_can_access_goods_receipts_for_own_branch(): void
    {
        $branchAdmin = User::factory()->create([
            'branch_id' => $this->branchOne->id,
            'role' => 'branch_admin',
        ]);

        $productBranchOne = Product::create([
            'branch_id' => $this->branchOne->id,
            'category_id' => $this->product->category_id,
            'sku' => 'PRD-BR1-01',
            'name' => 'Produk Cabang Satu',
            'purchase_price' => 30000,
            'selling_price' => 45000,
            'stock' => 5,
        ]);

        // 1. Can view index and create page
        $indexResponse = $this->actingAs($branchAdmin)->get('/purchases/goods-receipts');
        $indexResponse->assertStatus(200);

        $createResponse = $this->actingAs($branchAdmin)->get('/purchases/goods-receipts/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Produk Cabang Satu');

        // 2. Can store goods receipt for own branch
        $payload = [
            'supplier_name' => 'Supplier Lokal Cabang 1',
            'date' => now()->toDateString(),
            'payment_type' => 'cash',
            'reference_number' => 'GR-BR1-001',
            'items' => [
                [
                    'product_id' => $productBranchOne->id,
                    'quantity' => 10,
                    'unit_price' => 30000,
                ],
            ],
        ];

        $postResponse = $this->actingAs($branchAdmin)->post('/purchases/goods-receipts', $payload);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        $receipt = GoodsReceipt::where('reference_number', 'GR-BR1-001')->firstOrFail();
        $this->assertEquals($this->branchOne->id, $receipt->branch_id);

        // 3. Can view own branch show slip
        $showResponse = $this->actingAs($branchAdmin)->get(route('purchases.goods-receipts.show', $receipt->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('GR-BR1-001');
    }

    private function directPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => now()->toDateString(),
            'payment_type' => 'credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 50000],
            ],
        ], $overrides);
    }

    public function test_create_page_shows_supplier_dropdown(): void
    {
        Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'UD Tani Jaya',
            'is_active' => true,
        ]);

        $this->actingAs($this->master)->get('/purchases/goods-receipts/create')
            ->assertOk()
            ->assertSee('name="supplier_id"', false)
            ->assertSee('UD Tani Jaya');
    }

    public function test_direct_credit_receipt_without_supplier_id_is_rejected(): void
    {
        $this->actingAs($this->master)
            ->from('/purchases/goods-receipts/create')
            ->post('/purchases/goods-receipts', $this->directPayload(['supplier_name' => 'Teks Bebas Saja']))
            ->assertRedirect('/purchases/goods-receipts/create')
            ->assertSessionHasErrors('supplier_id');

        $this->assertSame(0, GoodsReceipt::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_direct_credit_receipt_rejects_inactive_or_unknown_supplier(): void
    {
        $inactive = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'Supplier Nonaktif',
            'is_active' => false,
        ]);

        foreach ([$inactive->id, 999999] as $supplierId) {
            $this->actingAs($this->master)
                ->post('/purchases/goods-receipts', $this->directPayload(['supplier_id' => $supplierId]))
                ->assertSessionHasErrors('supplier_id');
        }

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_branch_admin_cannot_charge_credit_to_other_branch_supplier(): void
    {
        $branchAdmin = User::factory()->create(['branch_id' => $this->branchOne->id, 'role' => 'branch_admin']);
        $centralSupplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'Supplier Milik Pusat',
            'is_active' => true,
        ]);

        $this->actingAs($branchAdmin)
            ->post('/purchases/goods-receipts', $this->directPayload(['supplier_id' => $centralSupplier->id]))
            ->assertSessionHasErrors('supplier_id');

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_direct_credit_receipt_links_supplier_and_shows_debt_in_supplier_hub(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'UD Tani Jaya',
            'is_active' => true,
        ]);

        $this->actingAs($this->master)
            ->post('/purchases/goods-receipts', $this->directPayload([
                'supplier_id' => $supplier->id,
                'supplier_name' => 'Nama Palsu Yang Diabaikan',
                'reference_number' => 'GR-TEMPO-001',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $receipt = GoodsReceipt::where('reference_number', 'GR-TEMPO-001')->firstOrFail();
        $this->assertSame($supplier->id, $receipt->supplier_id);
        $this->assertSame('UD Tani Jaya', $receipt->supplier_name);
        $this->assertSame('credit', $receipt->payment_type);

        $this->actingAs($this->master)
            ->get(route('backoffice.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Rp 200.000')
            ->assertSee('Terima Barang - Beras Organik 5kg');
    }

    public function test_direct_cash_receipt_still_accepts_free_text_supplier_name(): void
    {
        $this->actingAs($this->master)
            ->post('/purchases/goods-receipts', $this->directPayload([
                'payment_type' => 'cash',
                'supplier_name' => 'Warung Bu Sri',
                'reference_number' => 'GR-TUNAI-001',
            ]))
            ->assertSessionHasNoErrors();

        $receipt = GoodsReceipt::where('reference_number', 'GR-TUNAI-001')->firstOrFail();
        $this->assertNull($receipt->supplier_id);
        $this->assertSame('Warung Bu Sri', $receipt->supplier_name);
    }

    public function test_po_receipt_inherits_supplier_from_purchase_order(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'PT Pemasok PO',
            'is_active' => true,
        ]);
        $otherSupplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'name' => 'PT Penyusup',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-INHERIT-01',
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 100000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'received_quantity' => 0,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        $this->actingAs($this->master)
            ->post('/purchases/goods-receipts', $this->directPayload([
                'purchase_order_id' => $po->id,
                'payment_type' => 'cash',
                'supplier_id' => $otherSupplier->id,
                'reference_number' => 'GR-PO-INHERIT',
                'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50000]],
            ]))
            ->assertSessionHasNoErrors();

        $receipt = GoodsReceipt::where('reference_number', 'GR-PO-INHERIT')->firstOrFail();
        $this->assertSame($supplier->id, $receipt->supplier_id);
        $this->assertSame('credit', $receipt->payment_type);
    }

    public function test_branch_admin_cannot_view_goods_receipt_of_another_branch(): void
    {
        $branchAdmin = User::factory()->create([
            'branch_id' => $this->branchOne->id,
            'role' => 'branch_admin',
        ]);

        // Receipt belonging to central
        $centralReceipt = GoodsReceipt::create([
            'branch_id' => $this->central->id,
            'reference_number' => 'GR-CENTRAL-999',
            'supplier_name' => 'Pusat Supplier',
            'date' => now()->toDateString(),
            'total_amount' => 100000,
            'payment_type' => 'cash',
        ]);

        $response = $this->actingAs($branchAdmin)->get(route('purchases.goods-receipts.show', $centralReceipt->id));
        $response->assertStatus(403);
    }
}
