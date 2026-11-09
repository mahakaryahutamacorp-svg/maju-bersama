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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclassifyOpeningStockLossTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_opening_stock_losses_move_from_expense_to_equity(): void
    {
        $branch = Branch::create(['code' => 'GDG', 'name' => 'Gudang']);
        $user = User::factory()->create(['branch_id' => $branch->id, 'role' => 'master']);
        $expense = ChartOfAccount::create(['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense']);
        $inventory = ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '3110', 'name' => 'Modal Awal', 'type' => 'equity']);
        $category = Category::create(['name' => 'Pupuk', 'catalog' => Category::CATALOG_PUPUK]);
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'PB-1',
            'name' => 'Prima Best',
            'purchase_price' => 44000,
            'selling_price' => 50000,
            'stock' => 200,
        ]);
        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 200,
        ]);

        $opening = StockAdjustment::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'reference_number' => 'SA-OPEN-LOSS',
            'date' => '2026-11-08',
            'notes' => 'Input Stok Awal',
            'total_loss_value' => 29920000,
            'total_gain_value' => 0,
        ]);
        $openingJournal = JournalHeader::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'transaction_date' => '2026-11-08',
            'reference_number' => 'SA-OPEN-LOSS',
            'description' => 'Input Stok Awal #SA-OPEN-LOSS',
        ]);
        $openingJournal->journalLines()->createMany([
            ['chart_of_account_id' => $expense->id, 'debit' => 29920000, 'credit' => 0, 'memo' => 'Beban selisih rugi opname persediaan'],
            ['chart_of_account_id' => $inventory->id, 'debit' => 0, 'credit' => 29920000, 'memo' => 'Pengurangan persediaan fisik opname'],
        ]);
        $opening->update(['journal_header_id' => $openingJournal->id]);

        $opname = StockAdjustment::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'reference_number' => 'SA-OWNER',
            'date' => '2026-11-09',
            'notes' => 'Koreksi stok oleh owner',
            'total_loss_value' => 520000,
            'total_gain_value' => 0,
        ]);
        $opnameJournal = JournalHeader::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'transaction_date' => '2026-11-09',
            'reference_number' => 'SA-OWNER',
            'description' => 'Penyesuaian Stok (Opname) #SA-OWNER',
        ]);
        $opnameJournal->journalLines()->createMany([
            ['chart_of_account_id' => $expense->id, 'debit' => 520000, 'credit' => 0],
            ['chart_of_account_id' => $inventory->id, 'debit' => 0, 'credit' => 520000],
        ]);
        $opname->update(['journal_header_id' => $opnameJournal->id]);

        $this->artisan('stock:opening-loss-to-modal')
            ->expectsOutputToContain('SA-OPEN-LOSS')
            ->doesntExpectOutputToContain('SA-OWNER')
            ->assertSuccessful();
        $this->assertSame('5120', $openingJournal->journalLines()->first()->chartOfAccount->code);

        $this->artisan('stock:opening-loss-to-modal', ['--apply' => true])
            ->expectsConfirmation('1 jurnal input stok awal akan dipindahkan dari beban ke modal awal. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $openingLines = $openingJournal->fresh()->journalLines()->with('chartOfAccount')->get();
        $this->assertSame('3110', $openingLines->first(fn ($line) => (float) $line->debit > 0)->chartOfAccount->code);
        $this->assertSame('1210', $openingLines->first(fn ($line) => (float) $line->credit > 0)->chartOfAccount->code);
        $this->assertEquals(29920000, (float) $openingLines->sum('credit'));

        $opnameDebit = $opnameJournal->fresh()->journalLines()->with('chartOfAccount')->get()
            ->first(fn ($line) => (float) $line->debit > 0);
        $this->assertSame('5120', $opnameDebit->chartOfAccount->code);
        $this->assertSame(200, (int) Inventory::withoutGlobalScopes()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(200, $product->fresh()->stock);
    }
}
