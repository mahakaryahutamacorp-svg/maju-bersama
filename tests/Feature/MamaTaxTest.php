<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\InclusiveTaxCalculator;
use App\Services\SalesReturnService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MamaTaxTest extends TestCase
{
    public function test_inclusive_split_matches_the_owner_example(): void
    {
        $split = (new InclusiveTaxCalculator)->split('55500.00', '11.00');

        $this->assertSame('50000.00', $split['dpp']);
        $this->assertSame('5500.00', $split['tax']);
    }

    public function test_exempt_product_keeps_the_full_amount_in_revenue(): void
    {
        [$user, $product] = $this->branchWithProduct(taxEnabled: true, taxableProduct: false, code: 'GUDANG-PUPUK');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $saleId = $response->json('sale.id');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total_amount' => '110000.00',
            'subtotal_non_tax' => '110000.00',
            'dpp_taxable' => '0.00',
            'tax_total' => '0.00',
        ]);

        $journalId = JournalHeader::where('reference_number', $response->json('receipt_number'))->value('id');
        $lines = JournalLine::where('journal_header_id', $journalId)->get();
        $this->assertCount(2, $lines);
        $this->assertSame('110000.00', $lines->firstWhere('chart_of_account_id', $this->accountId('4110'))->credit);
        $this->assertNull(ChartOfAccount::where('code', '2130')->first());
    }

    public function test_mama_mixed_sale_splits_revenue_and_output_vat(): void
    {
        [$user, $fertilizer, $pesticide] = $this->mamaBasket();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $fertilizer->id, 'quantity' => 1],
                ['product_id' => $pesticide->id, 'quantity' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', '105500.00');

        $sale = Sale::query()->findOrFail($response->json('sale.id'));
        $this->assertSame('50000.00', $sale->subtotal_non_tax);
        $this->assertSame('50000.00', $sale->dpp_taxable);
        $this->assertSame('5500.00', $sale->tax_total);

        $pesticideLine = $sale->items->firstWhere('product_id', $pesticide->id);
        $this->assertTrue($pesticideLine->is_taxable);
        $this->assertSame('50000.00', $pesticideLine->dpp_amount);
        $this->assertSame('5500.00', $pesticideLine->tax_amount);

        $journalId = JournalHeader::where('reference_number', $sale->receipt_number)->value('id');
        $lines = JournalLine::where('journal_header_id', $journalId)->get();

        $this->assertSame('105500.00', $lines->firstWhere('chart_of_account_id', $this->accountId('1110'))->debit);
        $this->assertSame('100000.00', $lines->firstWhere('chart_of_account_id', $this->accountId('4110'))->credit);
        $this->assertSame('5500.00', $lines->firstWhere('chart_of_account_id', $this->accountId('2130'))->credit);
        $this->assertSame(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit'),
        );

        $balance = $this->getJson('/api/reports/balance-sheet')->assertOk();
        $income = $this->getJson('/api/reports/income-statement')->assertOk();

        $this->assertSame(0.0, (float) $balance->json('data.difference'));
        $this->assertTrue($balance->json('data.is_balanced'));
        $this->assertSame(100000.0, (float) $income->json('data.revenue.total'));
        $this->assertSame(100000.0, (float) $income->json('data.net_profit'));

        $vat = collect($balance->json('data.liabilities.accounts'))->firstWhere('code', '2130');
        $this->assertNotNull($vat);
        $this->assertSame(5500.0, (float) $vat['amount']);
    }

    public function test_taxable_discount_reduces_output_vat_and_books_only_the_dpp_portion(): void
    {
        [$user, $fertilizer, $pesticide] = $this->mamaBasket();
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $fertilizer->id, 'quantity' => 1],
                ['product_id' => $pesticide->id, 'quantity' => 1, 'discount_amount' => 5550],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', '99950.00');

        $sale = Sale::query()->latest('id')->first();
        $this->assertSame('50000.00', $sale->subtotal_non_tax);
        $this->assertSame('45000.00', $sale->dpp_taxable);
        $this->assertSame('4950.00', $sale->tax_total);

        $journalId = JournalHeader::where('reference_number', $sale->receipt_number)->value('id');
        $lines = JournalLine::where('journal_header_id', $journalId)->get();

        $this->assertSame('99950.00', $lines->firstWhere('chart_of_account_id', $this->accountId('1110'))->debit);
        $this->assertSame('5000.00', $lines->firstWhere('chart_of_account_id', $this->accountId('4130'))->debit);
        $this->assertSame('100000.00', $lines->firstWhere('chart_of_account_id', $this->accountId('4110'))->credit);
        $this->assertSame('4950.00', $lines->firstWhere('chart_of_account_id', $this->accountId('2130'))->credit);
        $this->assertSame((float) $lines->sum('debit'), (float) $lines->sum('credit'));
    }

    public function test_return_of_a_taxable_line_reverses_dpp_and_output_vat(): void
    {
        [$user, $fertilizer, $pesticide] = $this->mamaBasket();
        Sanctum::actingAs($user);

        $saleId = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $fertilizer->id, 'quantity' => 1],
                ['product_id' => $pesticide->id, 'quantity' => 1],
            ],
        ])->json('sale.id');

        $salesReturn = app(SalesReturnService::class)->processReturn(
            [
                'sale_id' => $saleId,
                'refund_method' => 'cash',
            ],
            [
                ['product_id' => $pesticide->id, 'quantity' => 1],
            ],
            $user,
        );

        $this->assertSame('55500.00', $salesReturn->total_amount);
        $this->assertSame('50000.00', $salesReturn->items->first()->dpp_amount);
        $this->assertSame('5500.00', $salesReturn->items->first()->tax_amount);

        $lines = $salesReturn->journalHeader->journalLines;
        $this->assertSame('50000.00', $lines->firstWhere('chartOfAccount.code', '4110')->debit);
        $this->assertSame('5500.00', $lines->firstWhere('chartOfAccount.code', '2130')->debit);
        $this->assertSame('55500.00', $lines->firstWhere('chartOfAccount.code', '1110')->credit);

        $balance = $this->getJson('/api/reports/balance-sheet')->assertOk();
        $vat = collect($balance->json('data.liabilities.accounts'))->firstWhere('code', '2130');
        $this->assertNull($vat);
        $this->assertSame(0.0, (float) $balance->json('data.difference'));
    }

    public function test_sales_report_filters_and_exports_taxable_lines_for_mama(): void
    {
        [$user, $fertilizer, $pesticide, $branch] = $this->mamaBasket(withBranch: true);
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Tani Makmur',
            'phone' => '0812000',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $fertilizer->id, 'quantity' => 1],
                ['product_id' => $pesticide->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $user->role = 'master';
        $user->save();
        $this->actingAs($user);

        $taxable = $this->get(route('reports.sales', [
            'branch_id' => $branch->id,
            'tax_scope' => 'taxable',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $taxable->assertOk();
        $taxable->assertSee('Khusus Pajak');
        $taxable->assertSee('Rp 55.500,00');
        $taxable->assertSee('Rp 50.000,00');
        $taxable->assertSee('Rp 5.500,00');
        $taxable->assertDontSee('Rp 105.500,00');

        $exempt = $this->get(route('reports.sales', [
            'branch_id' => $branch->id,
            'tax_scope' => 'exempt',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $exempt->assertOk();
        $exempt->assertSee('Rp 50.000,00');
        $exempt->assertDontSee('Rp 55.500,00');

        $export = $this->get(route('reports.sales.tax-export', [
            'branch_id' => $branch->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('Nama Pelanggan', $content);
        $this->assertStringContainsString('Tani Makmur', $content);
        $this->assertStringContainsString('Pestisida Uji', $content);
        $this->assertStringContainsString('50000.00', $content);
        $this->assertStringContainsString('5500.00', $content);
        $this->assertStringNotContainsString('Pupuk Uji', $content);
    }

    public function test_non_tax_checkbox_marks_a_product_exempt_on_any_branch(): void
    {
        $category = Category::create(['name' => 'Pestisida', 'catalog' => 'pupuk']);
        $warehouse = Branch::create(['code' => 'GUDANG-PUPUK', 'name' => 'Gudang']);
        $store = Branch::create(['code' => 'CBG-ABI', 'name' => 'ABI']);
        $admin = User::factory()->create([
            'branch_id' => $warehouse->id,
            'role' => 'branch_admin',
        ]);

        $this->actingAs($admin)->get(route('backoffice.products.create'))->assertSee('Non Pajak');
        $this->actingAs($admin)->post(route('backoffice.products.store'), [
            'name' => 'Pupuk Termasuk Pajak',
            'sku' => 'PPK-1',
            'category_id' => $category->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
        ])->assertRedirect();

        $this->assertTrue((bool) Product::withoutGlobalScopes()->where('sku', 'PPK-1')->value('is_taxable'));

        $this->actingAs($admin)->post(route('backoffice.products.store'), [
            'name' => 'Benih Bebas Pajak',
            'sku' => 'BNH-1',
            'category_id' => $category->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'is_tax_exempt' => '1',
        ])->assertRedirect();

        $this->assertFalse((bool) Product::withoutGlobalScopes()->where('sku', 'BNH-1')->value('is_taxable'));

        $storeAdmin = User::factory()->create([
            'branch_id' => $store->id,
            'role' => 'branch_admin',
        ]);
        $this->actingAs($storeAdmin)->get(route('backoffice.products.create'))->assertSee('Non Pajak');
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function branchWithProduct(bool $taxEnabled, bool $taxableProduct, string $code = 'CBG-X'): array
    {
        $this->seedAccounts();
        $branch = Branch::create([
            'code' => $code,
            'name' => 'Cabang Uji',
            'tax_enabled' => $taxEnabled,
            'tax_rate' => '11.00',
        ]);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
        ]);
        $category = Category::create(['name' => 'Umum']);
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'SKU-1',
            'name' => 'Produk Uji',
            'purchase_price' => 0,
            'selling_price' => 110000,
            'is_taxable' => $taxableProduct,
            'stock' => 5,
        ]);

        return [$user, $product];
    }

    /**
     * @return array{0: User, 1: Product, 2: Product, 3?: Branch}
     */
    private function mamaBasket(bool $withBranch = false): array
    {
        $this->seedAccounts();
        $branch = Branch::create([
            'code' => 'CBG-MAMA',
            'name' => 'TOKO MAJU BERSAMA MAMA',
            'tax_enabled' => true,
            'tax_rate' => '11.00',
        ]);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
        ]);
        $category = Category::create(['name' => 'Pertanian']);
        $fertilizer = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'PPK-NP',
            'name' => 'Pupuk Uji',
            'purchase_price' => 0,
            'selling_price' => 50000,
            'is_taxable' => false,
            'stock' => 5,
        ]);
        $pesticide = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'PST-NP',
            'name' => 'Pestisida Uji',
            'purchase_price' => 0,
            'selling_price' => 55500,
            'is_taxable' => true,
            'stock' => 5,
        ]);

        if ($withBranch) {
            return [$user, $fertilizer, $pesticide, $branch];
        }

        return [$user, $fertilizer, $pesticide];
    }

    private function seedAccounts(): void
    {
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '4110', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '4130', 'name' => 'Potongan Penjualan', 'type' => 'revenue', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function accountId(string $code): int
    {
        return (int) ChartOfAccount::where('code', $code)->value('id');
    }
}
