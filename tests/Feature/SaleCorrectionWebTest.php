<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCorrectionWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $master;

    private User $branchAdmin;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Toko Cabang Utama',
            'code' => 'CAB-UTAMA',
        ]);

        $this->master = User::create([
            'name' => 'Master Akun',
            'email' => 'master@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $this->branchAdmin = User::create([
            'name' => 'Admin Cabang',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        $cashier = User::create([
            'name' => 'Siti Kasir',
            'email' => 'siti@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Makanan Ringan',
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'SKU-SNACK-01',
            'name' => 'Keripik Singkong Balado',
            'selling_price' => 15000,
            'purchase_price' => 10000,
            'stock' => 25,
        ]);

        Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Gudang Samping',
            'code' => 'SAMPING',
        ]);

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Gudang Utama',
            'code' => 'UTAMA',
        ]);

        ChartOfAccount::updateOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '1130'], ['name' => 'Piutang Usaha', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);

        $this->actingAs($cashier)->postJson('/pos', [
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ])->assertCreated();
    }

    public function test_sales_history_shows_correction_actions_only_to_master(): void
    {
        $this->actingAs($this->master)
            ->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Ubah')
            ->assertSee('Hapus');

        $this->actingAs($this->branchAdmin)
            ->get(route('reports.sales'))
            ->assertOk()
            ->assertDontSee('Ubah')
            ->assertDontSee('Hapus');
    }

    public function test_branch_admin_cannot_void_a_sale(): void
    {
        $sale = Sale::query()->firstOrFail();

        $this->actingAs($this->branchAdmin)
            ->delete(route('reports.sales.destroy', $sale))
            ->assertForbidden();

        $this->assertEquals(23, (float) $this->product->fresh()->stock);
        $this->assertNotNull(Sale::query()->find($sale->id));
    }

    public function test_master_can_void_a_mistaken_sale_and_stock_returns_to_the_original_warehouse(): void
    {
        $sale = Sale::query()->firstOrFail();
        $inventory = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $this->assertSame($this->warehouse->id, (int) $inventory->warehouse_id);
        $this->assertEquals(23, (float) $inventory->quantity);

        $this->actingAs($this->master)
            ->delete(route('reports.sales.destroy', $sale))
            ->assertRedirect(route('reports.sales'))
            ->assertSessionHas('success');

        $inventory->refresh();
        $this->assertSame($this->warehouse->id, (int) $inventory->warehouse_id);
        $this->assertEquals(25, (float) $inventory->quantity);
        $this->assertEquals(25, (float) $this->product->fresh()->stock);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertSoftDeleted('journal_headers', [
            'reference_number' => $sale->receipt_number,
            'description' => 'POS Sale',
        ]);
    }

    public function test_master_can_reduce_quantity_and_the_difference_returns_to_stock(): void
    {
        $sale = Sale::with('items')->firstOrFail();
        $soldAt = $sale->created_at->toDateTimeString();

        $this->actingAs($this->master)
            ->put(route('reports.sales.update', $sale), [
                'payment_method' => 'cash',
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                        'price' => 15000,
                        'discount_amount' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('reports.sales.show', $sale))
            ->assertSessionHasNoErrors();

        $sale->refresh();
        $inventory = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $this->assertSame($soldAt, $sale->created_at->toDateTimeString());
        $this->assertEquals(15000, (float) $sale->total_amount);
        $this->assertEquals(1, (float) $sale->items()->first()->quantity);
        $this->assertEquals(24, (float) $inventory->quantity);
        $this->assertSame($this->warehouse->id, (int) $inventory->warehouse_id);
        $this->assertEquals(24, (float) $this->product->fresh()->stock);

        $journal = JournalHeader::with('journalLines')
            ->where('reference_number', $sale->receipt_number)
            ->where('description', 'POS Sale')
            ->firstOrFail();
        $this->assertEquals(
            round((float) $journal->journalLines->sum('debit'), 2),
            round((float) $journal->journalLines->sum('credit'), 2)
        );
        $this->assertEquals(1, JournalHeader::query()
            ->where('reference_number', $sale->receipt_number)
            ->where('description', 'POS Sale')
            ->count());
    }

    public function test_increasing_quantity_beyond_stock_keeps_the_original_sale(): void
    {
        $sale = Sale::with('items')->firstOrFail();

        $this->actingAs($this->master)
            ->from(route('reports.sales.edit', $sale))
            ->put(route('reports.sales.update', $sale), [
                'payment_method' => 'cash',
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 30,
                        'price' => 15000,
                        'discount_amount' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('reports.sales.edit', $sale))
            ->assertSessionHasErrors('items');

        $this->assertEquals(2, (float) $sale->fresh()->items()->first()->quantity);
        $this->assertEquals(23, (float) $this->product->fresh()->stock);
    }

    public function test_sale_with_a_return_cannot_be_voided(): void
    {
        $sale = Sale::query()->firstOrFail();
        $account = ChartOfAccount::where('code', '1110')->firstOrFail();

        SalesReturn::create([
            'branch_id' => $this->branch->id,
            'sale_id' => $sale->id,
            'user_id' => $this->master->id,
            'customer_name' => 'Pelanggan Umum',
            'return_date' => now()->toDateString(),
            'reference_number' => 'SR-LOCK-1',
            'refund_method' => 'cash',
            'chart_of_account_id' => $account->id,
            'total_amount' => 15000,
            'total_cost' => 10000,
            'status' => 'completed',
        ]);

        $this->actingAs($this->master)
            ->from(route('reports.sales'))
            ->delete(route('reports.sales.destroy', $sale))
            ->assertRedirect(route('reports.sales'))
            ->assertSessionHasErrors('sale');

        $this->assertNotNull(Sale::query()->find($sale->id));
        $this->assertEquals(23, (float) $this->product->fresh()->stock);
    }
}
