<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegisterShift;
use App\Models\CashTransfer;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionViewerWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;
    private Branch $branch2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create([
            'name'    => 'Cabang Jakarta Pusat',
            'code'    => 'JKT-01',
            'address' => 'Jl. Thamrin No. 1',
            'phone'   => '0811001122',
        ]);

        $this->branch2 = Branch::create([
            'name'    => 'Cabang Surabaya',
            'code'    => 'SBY-01',
            'address' => 'Jl. Basuki Rahmat No. 2',
            'phone'   => '0811001133',
        ]);

        $this->user = User::create([
            'name'      => 'Admin Utama',
            'email'     => 'admin@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'central_admin',
            'branch_id' => $this->branch1->id,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('transactions.details', 'INV-2026-0001'));
        $response->assertRedirect(route('login'));
    }

    public function test_viewer_resolves_sale_transaction_details(): void
    {
        $category = Category::create([
            'name' => 'Pupuk & Nutrisi',
            'slug' => 'pupuk-nutrisi',
        ]);

        $product = Product::create([
            'name'           => 'Pupuk Urea Petro 50kg',
            'sku'            => 'PUP-UREA-50',
            'category_id'    => $category->id,
            'branch_id'      => $this->branch1->id,
            'unit'           => 'sak',
            'purchase_price' => 120000,
            'selling_price'  => 150000,
            'stock'          => 100,
        ]);

        $register = \App\Models\CashRegister::create([
            'branch_id' => $this->branch1->id,
            'name'      => 'Kasir Utama',
            'is_active' => true,
        ]);

        $shift = CashRegisterShift::create([
            'branch_id' => $this->branch1->id,
            'cash_register_id' => $register->id,
            'user_id' => $this->user->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        $sale = Sale::create([
            'branch_id' => $this->branch1->id,
            'cash_register_shift_id' => $shift->id,
            'receipt_number' => 'INV-2026-0001',
            'total_amount' => 300000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 150000,
            'subtotal' => 300000,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', 'INV-2026-0001'));

        $response->assertOk()
            ->assertSee('Penjualan POS')
            ->assertSee('INV-2026-0001')
            ->assertSee('Pupuk Urea Petro 50kg')
            ->assertSee('PUP-UREA-50')
            ->assertSee('300.000')
            ->assertSee('Admin Utama');
    }

    public function test_viewer_resolves_stock_transfer_details(): void
    {
        $category = Category::create([
            'name' => 'Pestisida',
            'slug' => 'pestisida',
        ]);

        $product = Product::create([
            'name'           => 'Insektisida Curacron 500EC',
            'sku'            => 'INS-CUR-500',
            'category_id'    => $category->id,
            'branch_id'      => $this->branch1->id,
            'unit'           => 'botol',
            'purchase_price' => 50000,
            'selling_price'  => 75000,
            'stock'          => 50,
        ]);

        $transfer = StockTransfer::create([
            'reference_number' => 'TRF-STK-0001',
            'source_branch_id' => $this->branch1->id,
            'destination_branch_id' => $this->branch2->id,
            'created_by' => $this->user->id,
            'transfer_date' => now(),
            'status' => 'completed',
            'notes' => 'Permintaan stok darurat Cabang Surabaya',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'source_product_id' => $product->id,
            'destination_product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 60000,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', 'TRF-STK-0001'));

        $response->assertOk()
            ->assertSee('Transfer Stok Antar Gudang / Cabang')
            ->assertSee('TRF-STK-0001')
            ->assertSee('Insektisida Curacron 500EC')
            ->assertSee('Cabang Jakarta Pusat')
            ->assertSee('Cabang Surabaya')
            ->assertSee('Permintaan stok darurat');
    }

    public function test_viewer_resolves_expense_details(): void
    {
        $account = ChartOfAccount::create([
            'code' => '5101',
            'name' => 'Beban Listrik & Air',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $cashAccount = ChartOfAccount::create([
            'code' => '1111',
            'name' => 'Kas Operasional Harian',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $category = ExpenseCategory::create([
            'branch_id' => $this->branch1->id,
            'name' => 'Utilitas Kantor',
            'code' => 'UTIL-01',
            'chart_of_account_id' => $account->id,
            'is_active' => true,
        ]);

        $expense = Expense::create([
            'branch_id' => $this->branch1->id,
            'expense_category_id' => $category->id,
            'account_id' => $cashAccount->id,
            'amount' => 250000,
            'expense_date' => now(),
            'reference_number' => 'EXP-2026-0001',
            'notes' => 'Pembayaran tagihan listrik PLN bulan ini',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', 'EXP-2026-0001'));

        $response->assertOk()
            ->assertSee('Biaya Operasional (Kas Keluar)')
            ->assertSee('EXP-2026-0001')
            ->assertSee('Utilitas Kantor')
            ->assertSee('Kas Operasional Harian')
            ->assertSee('250.000')
            ->assertSee('Pembayaran tagihan listrik PLN');
    }

    public function test_viewer_resolves_cash_transfer_details(): void
    {
        $cashAccount = ChartOfAccount::create([
            'code' => '1112',
            'name' => 'Kas Laci Kasir',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $bankAccount = ChartOfAccount::create([
            'code' => '1121',
            'name' => 'Bank Mandiri Giro',
            'type' => 'asset',
            'is_active' => true,
        ]);

        CashTransfer::create([
            'branch_id' => $this->branch1->id,
            'from_account_id' => $cashAccount->id,
            'to_account_id' => $bankAccount->id,
            'amount' => 5000000,
            'transfer_date' => now(),
            'reference_number' => 'TRF-CSH-0001',
            'notes' => 'Setoran harian omzet kasir ke bank',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', 'TRF-CSH-0001'));

        $response->assertOk()
            ->assertSee('Mutasi Antar Kas &amp; Bank', false)
            ->assertSee('TRF-CSH-0001')
            ->assertSee('Kas Laci Kasir')
            ->assertSee('Bank Mandiri Giro')
            ->assertSee('5.000.000');
    }

    public function test_viewer_returns_not_found_for_unknown_reference(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('transactions.details', 'UNKNOWN-REF-999'));

        $response->assertOk()
            ->assertSee('Rincian Transaksi Tidak Ditemukan')
            ->assertSee('UNKNOWN-REF-999');
    }

    public function test_dashboard_renders_clickable_transaction_links_and_modal(): void
    {
        JournalHeader::create([
            'branch_id' => $this->branch1->id,
            'user_id' => $this->user->id,
            'transaction_date' => now(),
            'reference_number' => 'INV-2026-9999',
            'description' => 'Penjualan tunai POS #9999',
        ]);

        $response = $this->actingAs($this->user)->get('/backoffice');

        $response->assertOk()
            ->assertSee('loadTransaction(\'INV-2026-9999\')', false)
            ->assertSee('Universal Transaction Viewer')
            ->assertSee('transactionHtml', false);
    }
}
