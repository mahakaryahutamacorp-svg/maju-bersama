<?php

namespace Tests\Feature;

use App\Models\AssistantInquiry;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Assistant\AssistantContract;
use App\Services\Assistant\AssistantToolDenied;
use App\Services\Assistant\ReadOnlyToolkit;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadOnlyAssistantTest extends TestCase
{
    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Uji',
            'code' => 'UJI-01',
            'address' => 'Jl. Uji',
            'phone' => '0812000',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Cabang',
            'email' => 'admin-asisten@example.com',
            'password' => bcrypt('password'),
            'role' => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_contract_exposes_only_read_tools(): void
    {
        $this->assertSame([
            'search_guide',
            'stock_on_hand',
            'list_receivables',
            'sales_summary',
            'cash_position',
            'trace_document',
        ], AssistantContract::TOOLS);

        $this->expectException(AssistantToolDenied::class);
        app(ReadOnlyToolkit::class)->run('delete_sale', [], $this->admin);
    }

    public function test_guest_and_cashier_cannot_open_assistant(): void
    {
        $this->get(route('backoffice.assistant.index'))->assertRedirect(route('login'));

        $cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($cashier)
            ->get(route('backoffice.assistant.index'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->postJson(route('backoffice.assistant.ask'), ['question' => 'Di mana menu piutang?'])
            ->assertForbidden();
    }

    public function test_guide_question_returns_page_link_and_audit(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Di mana menu terima bayaran piutang?',
        ]);

        $response->assertOk()
            ->assertJsonPath('outcome', 'answered')
            ->assertJsonFragment(['label' => 'Terima Bayaran Piutang']);

        $this->assertStringContainsString(
            route('backoffice.payments.receivables.create'),
            $response->json('links.0.url')
        );

        $inquiry = AssistantInquiry::query()->first();
        $this->assertNotNull($inquiry);
        $this->assertSame('answered', $inquiry->outcome);
        $this->assertContains('search_guide', $inquiry->tools_used);
        $this->assertSame($this->admin->id, $inquiry->user_id);
    }

    public function test_action_request_does_not_record_payment(): void
    {
        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Tolong lunasi piutang pelanggan hari ini',
        ])->assertOk()
            ->assertJsonPath('outcome', 'refused_action');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('assistant_inquiries', ['outcome' => 'refused_action']);
    }

    public function test_question_outside_the_application_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Siapa presiden negara lain?',
        ])->assertOk()
            ->assertJsonPath('outcome', 'out_of_scope');

        $this->assertDatabaseHas('assistant_inquiries', [
            'outcome' => 'out_of_scope',
            'tools_used' => '[]',
        ]);
    }

    public function test_branch_admin_only_sees_own_stock_and_receivables(): void
    {
        $other = Branch::create([
            'name' => 'Cabang Lain',
            'code' => 'UJI-02',
            'address' => 'Jl. Lain',
            'phone' => '0812001',
        ]);
        $category = Category::create(['name' => 'Pupuk']);

        $ownProduct = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'SKU-UJI',
            'name' => 'Pupuk Uji',
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'stock' => 4,
        ]);
        $otherProduct = Product::withoutGlobalScopes()->create([
            'branch_id' => $other->id,
            'category_id' => $category->id,
            'sku' => 'SKU-LAIN',
            'name' => 'Pupuk Rahasia',
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'stock' => 9,
        ]);
        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $ownProduct->id,
            'quantity' => 4,
        ]);
        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $other->id,
            'product_id' => $otherProduct->id,
            'quantity' => 9,
        ]);

        $ownCustomer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bu Tini',
            'phone' => '08111',
        ]);
        $otherCustomer = Customer::withoutGlobalScopes()->create([
            'branch_id' => $other->id,
            'name' => 'Bu Rahasia',
            'phone' => '08222',
        ]);
        Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $ownCustomer->id,
            'receipt_number' => 'INV-UJI-01',
            'total_amount' => 250000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);
        Sale::withoutGlobalScopes()->create([
            'branch_id' => $other->id,
            'customer_id' => $otherCustomer->id,
            'receipt_number' => 'INV-LAIN-01',
            'total_amount' => 900000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        $stock = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Berapa stok barang?',
        ]);
        $stock->assertOk()->assertJsonPath('outcome', 'answered');
        $this->assertStringContainsString('Pupuk Uji', $stock->json('answer'));
        $this->assertStringNotContainsString('Pupuk Rahasia', $stock->json('answer'));

        $receivable = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Sisa piutang pelanggan',
        ]);
        $receivable->assertOk();
        $this->assertStringContainsString('Bu Tini', $receivable->json('answer'));
        $this->assertStringNotContainsString('Bu Rahasia', $receivable->json('answer'));
    }

    public function test_sales_and_cash_answers_use_live_branch_data(): void
    {
        Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-HARI-01',
            'total_amount' => 150000,
            'paid_amount' => 150000,
            'payment_status' => 'PAID',
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        $cash = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);
        $journal = JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JR-KAS-01',
            'description' => 'Saldo kas',
        ]);
        JournalLine::create([
            'journal_header_id' => $journal->id,
            'chart_of_account_id' => $cash->id,
            'debit' => 80000,
            'credit' => 0,
            'memo' => 'Kas masuk',
        ]);

        $sales = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Penjualan hari ini',
        ]);
        $sales->assertOk();
        $this->assertStringContainsString('Rp 150.000', $sales->json('answer'));

        $position = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Posisi kas dan bank',
        ]);
        $position->assertOk();
        $this->assertStringContainsString('Kas Toko', $position->json('answer'));
        $this->assertStringContainsString('Rp 80.000', $position->json('answer'));
    }

    public function test_trace_hides_journal_from_branch_admin_and_shows_it_to_master(): void
    {
        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bu Tini',
            'phone' => '08111',
        ]);
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'INV-TELUSUR-01',
            'total_amount' => 250000,
            'paid_amount' => 100000,
            'payment_status' => 'PARTIAL',
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);
        $account = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);
        $receivable = ChartOfAccount::create([
            'code' => '1130',
            'name' => 'Piutang Usaha',
            'type' => 'asset',
            'is_active' => true,
        ]);
        $payment = Payment::create([
            'branch_id' => $this->branch->id,
            'type' => 'AR',
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'reference_number' => 'AR-TELUSUR-01',
            'amount' => 100000,
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'sale_id' => $sale->id,
            'allocated_amount' => 100000,
        ]);
        $journal = JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $sale->receipt_number,
            'description' => 'Penjualan tempo',
        ]);
        JournalLine::create([
            'journal_header_id' => $journal->id,
            'chart_of_account_id' => $receivable->id,
            'debit' => 250000,
            'credit' => 0,
            'memo' => 'Piutang penjualan',
        ]);

        $adminTrace = $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Telusuri faktur INV-TELUSUR-01',
        ]);
        $adminTrace->assertOk()->assertJsonPath('outcome', 'answered');
        $this->assertStringContainsString('Bu Tini', $adminTrace->json('answer'));
        $this->assertStringContainsString('AR-TELUSUR-01', $adminTrace->json('answer'));
        $this->assertStringNotContainsString('[1130]', $adminTrace->json('answer'));

        $master = User::factory()->create([
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);
        $masterTrace = $this->actingAs($master)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Telusuri faktur INV-TELUSUR-01',
        ]);
        $this->assertStringContainsString('[1130] Piutang Usaha', $masterTrace->json('answer'));
    }

    public function test_local_assistant_ignores_api_key_until_remote_is_enabled(): void
    {
        config([
            'assistant.api_key' => 'test-key',
            'assistant.remote' => false,
        ]);
        Http::fake();

        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Di mana menu terima bayaran piutang?',
        ])->assertOk()->assertJsonPath('outcome', 'answered');

        Http::assertNothingSent();
    }

    public function test_model_cannot_invoke_a_write_tool(): void
    {
        config([
            'assistant.api_key' => 'test-key',
            'assistant.remote' => true,
        ]);
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'tool_calls' => [[
                            'function' => [
                                'name' => 'delete_sale',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Berapa stok barang pupuk?',
        ])->assertOk()->assertJsonPath('outcome', 'refused_action');

        $this->assertDatabaseCount('sales', 0);
        Http::assertSentCount(1);
    }

    public function test_hourly_limit_stops_extra_questions(): void
    {
        config(['assistant.hourly_limit' => 1]);

        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Apa arti status piutang?',
        ])->assertOk();

        $this->actingAs($this->admin)->postJson(route('backoffice.assistant.ask'), [
            'question' => 'Di mana menu stok barang?',
        ])->assertOk()->assertJsonPath('outcome', 'rate_limited');
    }
}
