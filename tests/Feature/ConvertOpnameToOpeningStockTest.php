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
use App\Services\StockAdjustmentService;
use Tests\TestCase;

class ConvertOpnameToOpeningStockTest extends TestCase
{
    private Branch $branch;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'CBG-ABI', 'name' => 'Toko ABI']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'branch_admin']);
        $category = Category::create(['name' => 'Pupuk', 'catalog' => Category::CATALOG_PUPUK]);
        $this->product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'UREA-1',
            'name' => 'Urea 50kg',
            'purchase_price' => 250000,
            'selling_price' => 275000,
            'stock' => 0,
        ]);
        Inventory::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity' => 0]);
    }

    public function test_lists_opname_documents_and_simulates_without_changes(): void
    {
        $opname = $this->opname(8, 'Masuk barang');
        $opening = $this->openingStock(2);

        $this->artisan('stock:opname-to-opening')
            ->expectsOutputToContain($opname->reference_number)
            ->doesntExpectOutputToContain($opening->reference_number)
            ->assertSuccessful();

        $this->artisan('stock:opname-to-opening', ['reference' => [$opname->reference_number]])
            ->expectsOutputToContain('Dr 1210 / Cr 3110')
            ->assertSuccessful();

        $this->assertSame('Masuk barang', $opname->fresh()->notes);
        $this->assertSame('4120', $this->creditAccountCode($opname->fresh()));

        $this->artisan('stock:opname-to-opening', ['reference' => [$opening->reference_number]])
            ->expectsOutputToContain('Sudah tercatat sebagai input stok awal')
            ->assertFailed();
    }

    public function test_apply_moves_the_journal_to_opening_equity_and_keeps_stock(): void
    {
        $opname = $this->opname(8, 'Masuk barang');

        $this->artisan('stock:opname-to-opening', ['reference' => [$opname->reference_number], '--apply' => true])
            ->expectsConfirmation('1 dokumen stok opname akan diubah menjadi input stok awal. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $opname->refresh();
        $this->assertSame('Input Stok Awal Masuk barang', $opname->notes);
        $this->assertSame(StockAdjustmentService::OPENING_STOCK_NOTE, $opname->lines()->value('reason'));
        $this->assertSame('3110', $this->creditAccountCode($opname));
        $this->assertStringStartsWith('Input Stok Awal #'.$opname->reference_number, $opname->journal->description);
        $this->assertEquals(2000000, (float) $opname->journal->journalLines->sum('credit'));
        $this->assertSame(8, (int) Inventory::withoutGlobalScopes()->where('product_id', $this->product->id)->value('quantity'));
        $this->assertSame(8, $this->product->fresh()->stock);

        $this->actingAs($this->admin)->get(route('inventory.opening-stock.create'))->assertOk();
    }

    public function test_auto_removes_the_journal_when_inventory_is_already_in_the_opening_balance(): void
    {
        $inventoryAccount = ChartOfAccount::firstOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        $equityAccount = ChartOfAccount::firstOrCreate(['code' => '3110'], ['name' => 'Modal Awal', 'type' => 'equity']);
        JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'transaction_date' => '2026-11-01',
            'reference_number' => 'OB-ABI',
            'description' => 'Saldo Awal Cabang',
        ])->journalLines()->createMany([
            ['chart_of_account_id' => $inventoryAccount->id, 'debit' => 5000000, 'credit' => 0],
            ['chart_of_account_id' => $equityAccount->id, 'debit' => 0, 'credit' => 5000000],
        ]);
        $opname = $this->opname(8, null);
        $journalId = $opname->journal_header_id;

        $this->artisan('stock:opname-to-opening', ['reference' => [$opname->reference_number], '--apply' => true])
            ->expectsOutputToContain('Dihapus (persediaan sudah di Saldo Awal)')
            ->expectsConfirmation('1 dokumen stok opname akan diubah menjadi input stok awal. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $opname->refresh();
        $this->assertSame('Input Stok Awal', $opname->notes);
        $this->assertNull($opname->journal_header_id);
        $this->assertSoftDeleted('journal_headers', ['id' => $journalId]);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    private function opname(int $quantity, ?string $notes): StockAdjustment
    {
        return app(StockAdjustmentService::class)->processAdjustment([
            'branch_id' => $this->branch->id,
            'date' => '2026-11-08',
            'notes' => $notes,
        ], [['product_id' => $this->product->id, 'actual_qty' => $quantity]], $this->admin);
    }

    private function openingStock(int $quantity): StockAdjustment
    {
        return app(StockAdjustmentService::class)->processAdjustment([
            'branch_id' => $this->branch->id,
            'date' => '2026-11-08',
            'notes' => StockAdjustmentService::OPENING_STOCK_NOTE,
            'is_opening_stock' => true,
        ], [['product_id' => $this->product->id, 'actual_qty' => $quantity, 'reason' => StockAdjustmentService::OPENING_STOCK_NOTE]], $this->admin);
    }

    private function creditAccountCode(StockAdjustment $adjustment): ?string
    {
        return $adjustment->journal->journalLines()->with('chartOfAccount')->get()
            ->firstWhere(fn ($line) => (float) $line->credit > 0)?->chartOfAccount?->code;
    }
}
