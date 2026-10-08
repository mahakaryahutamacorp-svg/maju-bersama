<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $user;

    private Category $category;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Utama',
            'code' => 'C01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'branch_admin',
        ]);

        $this->category = Category::create(['name' => 'Pupuk & Kimia']);

        $this->productA = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'NPK-001',
            'name' => 'Pupuk NPK 16-16-16',
            'purchase_price' => 15000,
            'selling_price' => 20000,
            'stock' => 25,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'UREA-001',
            'name' => 'Pupuk Urea Subsidi',
            'purchase_price' => 10000,
            'selling_price' => 13000,
            'stock' => 50,
        ]);
    }

    public function test_guest_cannot_access_supplier_or_purchase_orders(): void
    {
        $this->get('/backoffice/suppliers')->assertRedirect('/login');
        $this->get('/backoffice/purchase-orders')->assertRedirect('/login');
    }

    public function test_supplier_crud_lifecycle(): void
    {
        $this->actingAs($this->user);

        // 1. Index page loads
        $response = $this->get(route('backoffice.suppliers.index'));
        $response->assertOk();
        $response->assertSee('Buku Pemasok');

        // 2. Create page loads
        $response = $this->get(route('backoffice.suppliers.create'));
        $response->assertOk();
        $response->assertSee('Tambah Supplier Baru');

        // 3. Store supplier
        $storeResponse = $this->post(route('backoffice.suppliers.store'), [
            'name' => 'PT Pupuk Nusantara',
            'contact_person' => 'Hendro',
            'phone' => '0812999888',
            'address' => 'Kawasan Industri Gresik',
            'is_active' => 1,
        ]);

        $storeResponse->assertRedirect(route('backoffice.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Pupuk Nusantara',
            'branch_id' => $this->branch->id,
            'contact_person' => 'Hendro',
            'is_active' => 1,
        ]);

        $supplier = Supplier::where('name', 'PT Pupuk Nusantara')->first();

        // 4. Edit page loads
        $response = $this->get(route('backoffice.suppliers.edit', $supplier));
        $response->assertOk();
        $response->assertSee('Edit Data Supplier');

        // 5. Update supplier
        $updateResponse = $this->put(route('backoffice.suppliers.update', $supplier), [
            'name' => 'PT Pupuk Nusantara Jaya',
            'contact_person' => 'Hendro Pratama',
            'phone' => '0812999888',
            'address' => 'Kawasan Industri Gresik Baru',
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('backoffice.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'PT Pupuk Nusantara Jaya',
            'contact_person' => 'Hendro Pratama',
        ]);

        // 6. Delete supplier
        $deleteResponse = $this->delete(route('backoffice.suppliers.destroy', $supplier));
        $deleteResponse->assertRedirect(route('backoffice.suppliers.index'));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_purchase_order_create_screen_renders_with_suppliers_and_products(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'PT Agro Kimia',
            'is_active' => true,
        ]);

        $response = $this->get(route('backoffice.purchase-orders.create'));
        $response->assertOk();
        $response->assertSee('Penerbitan Purchase Order (PO)');
        $response->assertSee('PT Agro Kimia');
        $response->assertSee('Pupuk NPK 16-16-16');
        $response->assertSee('poForm');
    }

    public function test_purchase_order_store_creates_main_po_and_items_with_default_pending_status(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'PT Agro Kimia',
            'is_active' => true,
        ]);

        $postData = [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-09-19',
            'expected_date' => '2026-09-26',
            'notes' => 'Pengiriman mendesak untuk musim tanam',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => 15000,
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 20,
                    'unit_price' => 10000,
                ],
            ],
        ];

        $response = $this->post(route('backoffice.purchase-orders.store'), $postData);

        // Subtotal item A = 10 * 15000 = 150.000
        // Subtotal item B = 20 * 10000 = 200.000
        // Total amount = 350.000
        $po = PurchaseOrder::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($po);
        $this->assertEquals('pending', $po->status);
        $this->assertEquals('350000.00', $po->total_amount);
        $this->assertStringStartsWith('PO-', $po->reference_number);

        $response->assertRedirect(route('backoffice.purchase-orders.show', $po->id));

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_price' => 15000,
            'subtotal' => 150000,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'product_id' => $this->productB->id,
            'quantity' => 20,
            'received_quantity' => 0,
            'unit_price' => 10000,
            'subtotal' => 200000,
        ]);

        // Detail show slip renders
        $showResponse = $this->get(route('backoffice.purchase-orders.show', $po->id));
        $showResponse->assertOk();
        $showResponse->assertSee($po->reference_number);
        $showResponse->assertSee('PT Agro Kimia');
        $showResponse->assertSee('Pending');
        $showResponse->assertSee('Rp 350.000');
    }

    public function test_purchase_order_store_validation(): void
    {
        $this->actingAs($this->user);

        // Fails with empty payload
        $response = $this->post(route('backoffice.purchase-orders.store'), []);
        $response->assertSessionHasErrors(['supplier_id', 'order_date', 'items']);

        // Fails with empty items array
        $response = $this->post(route('backoffice.purchase-orders.store'), [
            'supplier_id' => 9999,
            'order_date' => '2026-09-19',
            'items' => [],
        ]);
        $response->assertSessionHasErrors(['supplier_id', 'items']);
    }

    public function test_purchase_order_pages_use_app_layout_with_step_label_and_breadcrumbs(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'CV Tani Makmur',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-LAYOUT-001',
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 150000,
        ]);

        $pages = [
            route('backoffice.purchase-orders.index'),
            route('backoffice.purchase-orders.create'),
            route('backoffice.purchase-orders.show', $po->id),
        ];

        foreach ($pages as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('aria-label="Breadcrumb"', false)
                ->assertSeeInOrder(['Beranda', 'Belanja Barang', 'Pesan Barang'])
                ->assertSee('Langkah 1: Pesan Barang (PO)')
                ->assertSee('Keluar');
        }

        $this->get(route('backoffice.purchase-orders.index'))
            ->assertSee('PO-LAYOUT-001')
            ->assertSee(route('backoffice.purchase-orders.create'), false)
            ->assertSee(route('backoffice.purchase-orders.destroy', $po), false);
    }

    public function test_purchase_order_without_goods_receipt_can_be_deleted_from_index(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'CV Hapus PO',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-DEL-001',
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 50000,
        ]);

        $this->delete(route('backoffice.purchase-orders.destroy', $po))
            ->assertRedirect(route('backoffice.purchase-orders.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
    }

    public function test_cashier_cannot_open_purchase_order_pages(): void
    {
        $cashier = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'cashier',
        ]);

        $this->actingAs($cashier);

        $this->get(route('backoffice.purchase-orders.index'))->assertForbidden();
        $this->get(route('backoffice.purchase-orders.create'))->assertForbidden();
        $this->post(route('backoffice.purchase-orders.store'), [])->assertForbidden();
    }
}
