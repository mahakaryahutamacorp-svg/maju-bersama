<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    private Branch $central;
    private Branch $branchOne;
    private Category $category;
    private User $master;
    private Product $centralProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Branch::create(['code' => 'PUSAT', 'name' => 'majubersamapusat']);
        $this->branchOne = Branch::create([
            'code' => 'MAJUBERSAMA-1',
            'name' => 'majubersama 1',
            'parent_id' => $this->central->id,
        ]);

        $this->category = Category::create(['name' => 'Pertanian']);

        ChartOfAccount::insert([
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
        ]);

        $this->master = User::factory()->create([
            'branch_id' => $this->central->id,
            'role' => 'master',
        ]);

        $this->centralProduct = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'category_id' => $this->category->id,
            'sku' => 'PRT-001',
            'name' => 'Pupuk Organik 25kg',
            'purchase_price' => 85000,
            'selling_price' => 110000,
            'stock' => 50,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->central->id,
            'product_id' => $this->centralProduct->id,
            'quantity' => 50,
        ]);
    }

    private function destinationProduct(): Product
    {
        return Product::withoutGlobalScopes()
            ->where('branch_id', $this->branchOne->id)
            ->where('sku', 'PRT-001')
            ->firstOrFail();
    }

    public function test_transfer_moves_stock_from_central_to_branch(): void
    {
        Sanctum::actingAs($this->master);

        $response = $this->postJson('/api/stock-transfers', [
            'source_branch_id' => $this->central->id,
            'destination_branch_id' => $this->branchOne->id,
            'items' => [
                ['product_id' => $this->centralProduct->id, 'quantity' => 20],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['reference_number', 'data' => ['id', 'items']]);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->central->id,
            'product_id' => $this->centralProduct->id,
            'quantity' => 30,
        ]);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->branchOne->id,
            'product_id' => $this->destinationProduct()->id,
            'quantity' => 20,
        ]);
    }

    public function test_repeated_transfers_do_not_duplicate_inventory_rows(): void
    {
        Sanctum::actingAs($this->master);

        foreach ([5, 7, 3] as $quantity) {
            $this->postJson('/api/stock-transfers', [
                'source_branch_id' => $this->central->id,
                'destination_branch_id' => $this->branchOne->id,
                'items' => [
                    ['product_id' => $this->centralProduct->id, 'quantity' => $quantity],
                ],
            ])->assertCreated();
        }

        $destinationProduct = $this->destinationProduct();

        // One row per (branch_id, product_id) pair, never one row per transfer.
        $this->assertSame(1, Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branchOne->id)
            ->where('product_id', $destinationProduct->id)
            ->count());

        $this->assertSame(1, Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->central->id)
            ->where('product_id', $this->centralProduct->id)
            ->count());

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->branchOne->id,
            'product_id' => $destinationProduct->id,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->central->id,
            'product_id' => $this->centralProduct->id,
            'quantity' => 35,
        ]);
    }

    public function test_transfer_writes_balanced_journals_for_both_branches(): void
    {
        Sanctum::actingAs($this->master);

        $this->postJson('/api/stock-transfers', [
            'source_branch_id' => $this->central->id,
            'destination_branch_id' => $this->branchOne->id,
            'items' => [
                ['product_id' => $this->centralProduct->id, 'quantity' => 10],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('journal_headers', [
            'branch_id' => $this->central->id,
            'description' => 'Stock transfer out',
        ]);

        $this->assertDatabaseHas('journal_headers', [
            'branch_id' => $this->branchOne->id,
            'description' => 'Stock transfer in',
        ]);

        // 10 units x 85000 purchase price = 850000 on each side of both journals.
        $this->assertDatabaseHas('journal_lines', ['debit' => 850000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['debit' => 0, 'credit' => 850000]);
    }

    public function test_transfer_is_rolled_back_when_stock_is_insufficient(): void
    {
        Sanctum::actingAs($this->master);

        $response = $this->postJson('/api/stock-transfers', [
            'source_branch_id' => $this->central->id,
            'destination_branch_id' => $this->branchOne->id,
            'items' => [
                ['product_id' => $this->centralProduct->id, 'quantity' => 500],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->central->id,
            'product_id' => $this->centralProduct->id,
            'quantity' => 50,
        ]);

        $this->assertDatabaseCount('stock_transfers', 0);
        $this->assertDatabaseCount('stock_transfer_items', 0);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_branch_user_cannot_transfer_out_of_another_branch(): void
    {
        $branchUser = User::factory()->create([
            'branch_id' => $this->branchOne->id,
            'role' => 'branch_admin',
        ]);

        Sanctum::actingAs($branchUser);

        $response = $this->postJson('/api/stock-transfers', [
            'source_branch_id' => $this->central->id,
            'destination_branch_id' => $this->branchOne->id,
            'items' => [
                ['product_id' => $this->centralProduct->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('source_branch_id');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_transfer_rejects_identical_source_and_destination(): void
    {
        Sanctum::actingAs($this->master);

        $response = $this->postJson('/api/stock-transfers', [
            'source_branch_id' => $this->central->id,
            'destination_branch_id' => $this->central->id,
            'items' => [
                ['product_id' => $this->centralProduct->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('destination_branch_id');
    }

    public function test_transfer_page_renders_with_available_stock(): void
    {
        $response = $this->actingAs($this->master)->get('/inventory/transfer');

        $response->assertOk()
            ->assertSee('Stock Transfer')
            ->assertSee('PRT-001');
    }

    public function test_guest_cannot_open_transfer_page(): void
    {
        $this->get('/inventory/transfer')->assertRedirect('/login');
    }
}
