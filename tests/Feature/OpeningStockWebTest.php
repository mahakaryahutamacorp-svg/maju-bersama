<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningStockWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '3110', 'name' => 'Modal Awal', 'type' => 'equity']);

        $this->branch = Branch::create(['name' => 'Gudang Pusat', 'code' => 'PUSAT', 'address' => 'Jl. Pusat', 'phone' => '0811']);

        $this->admin = User::create([
            'name' => 'Admin Gudang',
            'email' => 'gudang@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Herbisida']);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'PST-012',
            'name' => 'Gramoxone 1 Liter',
            'purchase_price' => 70000,
            'selling_price' => 82000,
            'stock' => 0,
        ]);

        Inventory::create(['branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity' => 0]);
    }

    public function test_page_lists_branch_products(): void
    {
        $this->actingAs($this->admin)
            ->get('/inventory/opening-stock')
            ->assertOk()
            ->assertSee('Input Stok Awal')
            ->assertSee('Gramoxone 1 Liter')
            ->assertSee('Herbisida');
    }

    public function test_store_sets_stock_and_posts_inventory_against_opening_equity(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/opening-stock', [
            'branch_id' => $this->branch->id,
            'date' => '2026-11-08',
            'record_journal' => 1,
            'items' => [['product_id' => $this->product->id, 'quantity' => 12]],
        ]);

        $adjustment = StockAdjustment::firstOrFail();
        $response->assertRedirect("/inventory/adjustments/{$adjustment->id}");

        $this->assertSame(12, (int) Inventory::where('product_id', $this->product->id)->value('quantity'));
        $this->product->refresh();
        $this->assertSame(12, (int) $this->product->stock);
        $this->assertEquals(70000, (float) $this->product->purchase_price);
        $this->assertEquals(82000, (float) $this->product->selling_price);

        $journal = JournalHeader::with('journalLines.chartOfAccount')->findOrFail($adjustment->journal_header_id);
        $debit = $journal->journalLines->firstWhere(fn ($line) => $line->chartOfAccount->code === '1210');
        $credit = $journal->journalLines->firstWhere(fn ($line) => $line->chartOfAccount->code === '3110');
        $this->assertEquals(840000, (float) $debit->debit);
        $this->assertEquals(840000, (float) $credit->credit);

        $this->assertFalse(OpeningBalanceService::hasOpeningBalance($this->branch->id));
    }

    public function test_store_without_journal_only_updates_stock(): void
    {
        $this->actingAs($this->admin)->post('/inventory/opening-stock', [
            'branch_id' => $this->branch->id,
            'date' => '2026-11-08',
            'record_journal' => 0,
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, (int) Inventory::where('product_id', $this->product->id)->value('quantity'));
        $this->assertNull(StockAdjustment::firstOrFail()->journal_header_id);
        $this->assertSame(0, JournalHeader::count());
    }

    public function test_store_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin)->post('/inventory/opening-stock', [
            'branch_id' => $this->branch->id,
            'date' => '2026-11-08',
            'record_journal' => 1,
            'items' => [],
        ])->assertSessionHasErrors('items');
    }
}
