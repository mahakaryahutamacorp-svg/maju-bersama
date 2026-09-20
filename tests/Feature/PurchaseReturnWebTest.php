<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private Supplier $supplier;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Utama Surabaya',
            'code' => 'SBY01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'branch_admin',
        ]);

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'CV Sumber Makmur',
            'contact_person' => 'Joko',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $this->category = Category::create(['name' => 'Bahan Pokok']);

        $this->product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'GULA-01',
            'name' => 'Gula Pasir 1kg',
            'purchase_price' => 15000,
            'selling_price' => 18000,
            'stock' => 50,
            'is_active' => true,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);
    }

    public function test_guest_cannot_access_purchase_returns(): void
    {
        $response = $this->get(route('backoffice.purchase-returns.index'));
        $response->assertRedirect('/login');

        $response = $this->get(route('backoffice.purchase-returns.create'));
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_index_screen(): void
    {
        PurchaseReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'PRT-20260921-99999',
            'return_date' => '2026-09-21',
            'total_amount' => 75000,
            'status' => 'completed',
            'notes' => 'Gula kemasan bocor',
        ]);

        $response = $this->actingAs($this->user)->get(route('backoffice.purchase-returns.index'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Retur Pembelian');
        $response->assertSee('PRT-20260921-99999');
        $response->assertSee('CV Sumber Makmur');
        $response->assertSee('75.000');
        $response->assertSee('loadTransaction');
    }

    public function test_user_can_view_create_screen_with_suppliers_and_products(): void
    {
        $response = $this->actingAs($this->user)->get(route('backoffice.purchase-returns.create'));

        $response->assertStatus(200);
        $response->assertSee('Input Retur Pembelian');
        $response->assertSee('CV Sumber Makmur');
        $response->assertSee('Gula Pasir 1kg');
        $response->assertSee('Live Journal Preview');
        $response->assertSee('purchaseReturnForm');
    }

    public function test_user_can_store_purchase_return(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'return_date' => '2026-09-21',
            'notes' => 'Retur batch 5 bungkus gula kemasan rusak',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 15000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('backoffice.purchase-returns.store'), $payload);

        $response->assertRedirect(route('backoffice.purchase-returns.index'));
        $response->assertSessionHas('success');

        // Check purchase_returns
        $this->assertDatabaseHas('purchase_returns', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'total_amount' => '75000.00',
            'status' => 'completed',
        ]);

        // Check purchase_return_items
        $this->assertDatabaseHas('purchase_return_items', [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => '15000.00',
            'subtotal' => '75000.00',
        ]);

        // Check stock reduced: 50 - 5 = 45
        $this->product->refresh();
        $this->assertEquals(45, $this->product->stock);

        $inv = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(45, $inv->quantity);

        // Check journal created
        $ret = PurchaseReturn::withoutGlobalScopes()->where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($ret->journal_header_id);

        $lines = $ret->journalHeader->journalLines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('chartOfAccount.code', PurchaseReturnService::ACCOUNT_PAYABLE);
        $creditLine = $lines->firstWhere('chartOfAccount.code', PurchaseReturnService::ACCOUNT_INVENTORY);
        $this->assertEquals('75000.00', $debitLine->debit);
        $this->assertEquals('75000.00', $creditLine->credit);
    }

    public function test_store_validation_fails_for_invalid_input(): void
    {
        // 1. Missing items
        $response = $this->actingAs($this->user)->post(route('backoffice.purchase-returns.store'), [
            'supplier_id' => $this->supplier->id,
            'return_date' => '2026-09-21',
            'items' => [],
        ]);
        $response->assertSessionHasErrors(['items']);

        // 2. Missing supplier
        $response = $this->actingAs($this->user)->post(route('backoffice.purchase-returns.store'), [
            'supplier_id' => 99999,
            'return_date' => '2026-09-21',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 10000],
            ],
        ]);
        $response->assertSessionHasErrors(['supplier_id']);
    }

    public function test_transaction_viewer_shows_purchase_return_details(): void
    {
        $service = app(PurchaseReturnService::class);
        $purchaseReturn = $service->processReturn(
            [
                'branch_id' => $this->branch->id,
                'supplier_id' => $this->supplier->id,
                'return_date' => '2026-09-21',
                'notes' => 'Viewer modal test notes',
            ],
            [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 15000,
                ],
            ],
            $this->user
        );

        $response = $this->actingAs($this->user)->get("/backoffice/transactions/{$purchaseReturn->reference_number}/details");

        $response->assertStatus(200);
        $response->assertSee('Retur Pembelian (Purchase Return)');
        $response->assertSee($purchaseReturn->reference_number);
        $response->assertSee('CV Sumber Makmur');
        $response->assertSee('Gula Pasir 1kg');
        $response->assertSee('30.000');
        $response->assertSee('Viewer modal test notes');
        $response->assertSee('Jurnal Akuntansi Otomatis');
        $response->assertSee(PurchaseReturnService::ACCOUNT_PAYABLE);
        $response->assertSee(PurchaseReturnService::ACCOUNT_INVENTORY);
    }
}
