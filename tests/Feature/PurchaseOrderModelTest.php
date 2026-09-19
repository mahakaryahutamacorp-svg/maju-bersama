<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderModelTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private User $userA;
    private User $masterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create([
            'name' => 'Cabang Utama',
            'code' => 'C01',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'name' => 'Cabang Kedua',
            'code' => 'C02',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'branch_admin',
        ]);

        $this->masterUser = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'master',
        ]);
    }

    public function test_supplier_creation_and_attributes(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'PT Sumber Rejeki',
            'contact_person' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Pahlawan No. 12, Surabaya',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'branch_id' => $this->branchA->id,
            'name' => 'PT Sumber Rejeki',
            'contact_person' => 'Budi Santoso',
            'is_active' => 1,
        ]);

        $this->assertInstanceOf(Branch::class, $supplier->branch);
        $this->assertEquals($this->branchA->id, $supplier->branch->id);
    }

    public function test_supplier_has_branch_scope(): void
    {
        Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'Supplier Branch A',
        ]);

        Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'name' => 'Supplier Branch B',
        ]);

        // Branch A user should only see Supplier Branch A
        $this->actingAs($this->userA);
        $this->assertCount(1, Supplier::all());
        $this->assertEquals('Supplier Branch A', Supplier::first()->name);

        // Master user should see all suppliers
        $this->actingAs($this->masterUser);
        $this->assertCount(2, Supplier::all());
    }

    public function test_purchase_order_creation_and_relations(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'PT Maju Distributor',
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-2026-0001',
            'order_date' => '2026-09-19',
            'expected_date' => '2026-09-25',
            'status' => 'draft',
            'total_amount' => 500000,
            'notes' => 'Pesanan rutin bulanan',
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'reference_number' => 'PO-2026-0001',
            'status' => 'draft',
            'total_amount' => 500000,
        ]);

        $this->assertInstanceOf(Supplier::class, $po->supplier);
        $this->assertEquals($supplier->id, $po->supplier->id);
        $this->assertInstanceOf(Branch::class, $po->branch);
        $this->assertEquals($this->branchA->id, $po->branch->id);
    }

    public function test_purchase_order_has_branch_scope(): void
    {
        $supplierA = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'Supplier A',
        ]);

        $supplierB = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'name' => 'Supplier B',
        ]);

        PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplierA->id,
            'reference_number' => 'PO-A-001',
            'order_date' => '2026-09-19',
            'status' => 'pending',
            'total_amount' => 100000,
        ]);

        PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'supplier_id' => $supplierB->id,
            'reference_number' => 'PO-B-001',
            'order_date' => '2026-09-19',
            'status' => 'pending',
            'total_amount' => 200000,
        ]);

        // Branch A user sees only PO from Branch A
        $this->actingAs($this->userA);
        $this->assertCount(1, PurchaseOrder::all());
        $this->assertEquals('PO-A-001', PurchaseOrder::first()->reference_number);

        // Master user sees all POs
        $this->actingAs($this->masterUser);
        $this->assertCount(2, PurchaseOrder::all());
    }

    public function test_purchase_order_items_and_cascade_delete(): void
    {
        $category = Category::create(['name' => 'Makanan']);
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'category_id' => $category->id,
            'sku' => 'INDOMIE-01',
            'name' => 'Indomie Goreng',
            'purchase_price' => 2500,
            'selling_price' => 3500,
            'stock' => 50,
        ]);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'PT Indofood',
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-INDO-001',
            'order_date' => '2026-09-19',
            'status' => 'pending',
            'total_amount' => 250000,
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 2500,
            'subtotal' => 250000,
        ]);

        $this->assertCount(1, $po->items);
        $this->assertEquals($item->id, $po->items->first()->id);
        $this->assertEquals($product->id, $item->product->id);
        $this->assertEquals('Indomie Goreng', $item->product->name);

        // Test cascade delete
        $po->delete();
        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['id' => $item->id]);
        // Product remains intact
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_goods_receipt_links_to_purchase_order(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'PT Mitra Sejati',
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-RCV-001',
            'order_date' => '2026-09-19',
            'status' => 'pending',
            'total_amount' => 150000,
        ]);

        $gr = GoodsReceipt::create([
            'branch_id' => $this->branchA->id,
            'purchase_order_id' => $po->id,
            'reference_number' => 'GR-2026-0001',
            'supplier_name' => 'PT Mitra Sejati',
            'date' => '2026-09-20',
            'total_amount' => 150000,
            'payment_type' => 'credit',
        ]);

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $gr->id,
            'purchase_order_id' => $po->id,
        ]);

        $this->assertInstanceOf(PurchaseOrder::class, $gr->purchaseOrder);
        $this->assertEquals($po->id, $gr->purchaseOrder->id);
        $this->assertCount(1, $po->goodsReceipts);
        $this->assertEquals($gr->id, $po->goodsReceipts->first()->id);

        // Test nullOnDelete when PO is deleted
        $po->delete();
        $gr->refresh();
        $this->assertNull($gr->purchase_order_id);
    }
}
