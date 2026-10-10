<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class IntegrityGapApiTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_tc010_product_and_customer_with_history_cannot_be_removed(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $product = $this->sellableProduct($branch, 5);
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Pelanggan Berriwayat',
        ]);

        $sale = Sale::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'INV-TC010',
            'total_amount' => 110000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $master->id,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 110000,
            'subtotal' => 110000,
        ]);

        Sanctum::actingAs($master);
        $this->deleteJson('/api/products/'.$product->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('product')
            ->assertJsonPath('errors.product.0', Product::HISTORY_DELETE_MESSAGE);

        $this->actingAs($master)
            ->delete(route('backoffice.products.destroy', $product->id))
            ->assertRedirect(route('backoffice.products.index'))
            ->assertSessionHas('error', Product::HISTORY_DELETE_MESSAGE);

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);

        $this->actingAs($master)
            ->delete(route('backoffice.customers.destroy', $customer->id))
            ->assertRedirect(route('backoffice.customers.index'))
            ->assertSessionHas('error', Customer::HISTORY_DELETE_MESSAGE);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
    }

    public function test_tc010_product_received_from_supplier_cannot_be_removed(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $product = $this->sellableProduct($branch, 5);

        $receipt = GoodsReceipt::create([
            'branch_id' => $branch->id,
            'reference_number' => 'GR-TC010',
            'date' => '2026-10-10',
            'total_amount' => '25000.00',
            'payment_type' => 'cash',
        ]);
        $receipt->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '25000.00',
            'subtotal' => '25000.00',
        ]);

        Sanctum::actingAs($master);
        $this->deleteJson('/api/products/'.$product->id)
            ->assertStatus(422)
            ->assertJsonPath('errors.product.0', Product::HISTORY_DELETE_MESSAGE);

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_tc010_customer_with_receivable_payment_cannot_be_removed(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Pelanggan Pelunasan Piutang',
        ]);

        Payment::create([
            'branch_id' => $branch->id,
            'type' => 'AR',
            'customer_id' => $customer->id,
            'account_id' => ChartOfAccount::where('code', '1110')->value('id'),
            'payment_date' => '2026-10-10',
            'reference_number' => 'AR-TC010',
            'amount' => '50000.00',
        ]);

        $this->actingAs($master)
            ->delete(route('backoffice.customers.destroy', $customer->id))
            ->assertRedirect(route('backoffice.customers.index'))
            ->assertSessionHas('error', Customer::HISTORY_DELETE_MESSAGE);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_tc010_product_and_customer_without_history_can_still_be_removed(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $product = $this->sellableProduct($branch, 0);
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Pelanggan Baru',
        ]);

        Sanctum::actingAs($master);
        $this->deleteJson('/api/products/'.$product->id)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->actingAs($master)
            ->delete(route('backoffice.customers.destroy', $customer->id))
            ->assertRedirect(route('backoffice.customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_tc010_product_list_disables_delete_for_products_with_history(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $usedProduct = $this->sellableProduct($branch, 5);
        $freshProduct = $this->sellableProduct($branch, 0);

        $sale = Sale::create([
            'branch_id' => $branch->id,
            'receipt_number' => 'INV-TC010-UI',
            'total_amount' => 110000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $master->id,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $usedProduct->id,
            'quantity' => 1,
            'price' => 110000,
            'subtotal' => 110000,
        ]);

        $this->actingAs($master)
            ->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertSee('sudah memiliki riwayat transaksi sehingga tidak dapat dihapus', false)
            ->assertSee('action="'.route('backoffice.products.destroy', $freshProduct->id).'"', false)
            ->assertDontSee('action="'.route('backoffice.products.destroy', $usedProduct->id).'"', false);
    }

    public function test_tc011_pos_money_columns_are_decimal_like_the_journal(): void
    {
        $tables = [
            'sales' => ['total_amount', 'discount_amount', 'paid_amount'],
            'sale_items' => ['price', 'subtotal'],
            'journal_lines' => ['debit', 'credit'],
        ];

        foreach ($tables as $table => $moneyColumns) {
            $columns = collect(Schema::getColumns($table))->keyBy('name');

            foreach ($columns as $column) {
                $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
                $this->assertStringNotContainsString('float', $type);
                $this->assertStringNotContainsString('double', $type);
            }

            foreach ($moneyColumns as $name) {
                $type = strtolower((string) ($columns[$name]['type_name'] ?? ''));
                $this->assertContains($type, ['decimal', 'numeric'], "{$table}.{$name} must be DECIMAL.");
            }
        }
    }

    public function test_tc011_fractional_price_is_kept_to_the_sen_in_sale_and_journal(): void
    {
        $this->assertCheckoutKeepsSen('10.50', 1, '10.50');
    }

    public function test_tc011_repeating_sen_multiplies_without_rounding_drift(): void
    {
        $this->assertCheckoutKeepsSen('33.33', 3, '99.99');
    }

    private function assertCheckoutKeepsSen(string $unitPrice, int $quantity, string $expectedTotal): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $product = $this->sellableProduct($branch, 5, $unitPrice, '0.00');
        Sanctum::actingAs($master);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ])->assertCreated()
            ->assertJsonPath('sale.total_amount', $expectedTotal)
            ->assertJsonPath('sale.items.0.price', $unitPrice)
            ->assertJsonPath('sale.items.0.subtotal', $expectedTotal);

        $sale = Sale::withoutGlobalScopes()->findOrFail($response->json('sale.id'));
        $item = $sale->items()->firstOrFail();

        $this->assertSame($expectedTotal, $sale->total_amount);
        $this->assertSame($expectedTotal, $sale->paid_amount);
        $this->assertSame($unitPrice, $item->price);
        $this->assertSame($expectedTotal, $item->subtotal);

        $lines = JournalLine::query()
            ->whereHas('journalHeader', fn ($query) => $query->where('reference_number', $sale->receipt_number))
            ->get();
        $cashDebit = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1110')->value('id'));
        $revenueCredit = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '4110')->value('id'));

        $this->assertNotNull($cashDebit);
        $this->assertNotNull($revenueCredit);
        $this->assertMoneySame($cashDebit->debit, $sale->total_amount);
        $this->assertMoneySame($revenueCredit->credit, $item->subtotal);
        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
    }

    public function test_tc012_tiered_discount_split_payment_and_project_memory_are_open_gaps(): void
    {
        // TODO TC012: diskon bertingkat, pembayaran split kas+bank, studi Odoo/ERPNext,
        // stok satu produk di dua gudang satu cabang, dan catatan bug skala desimal
        // serta beranda akun pusat tanpa cabang belum selesai.
        $this->markTestIncomplete('Sesuai TC012: Fitur belum dimodelkan atau masih berupa celah.');
    }
}
