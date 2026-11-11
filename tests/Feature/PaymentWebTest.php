<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalHeader;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ARAPPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $user;

    private ChartOfAccount $kasAccount;

    private ChartOfAccount $bankAccount;

    private ChartOfAccount $arAccount;

    private ChartOfAccount $apAccount;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Utama Bandung',
            'code' => 'BDG-01',
            'address' => 'Jl. Asia Afrika No. 10',
            'phone' => '0812345678',
        ]);

        $this->user = User::create([
            'name' => 'Finance Officer',
            'email' => 'finance@example.com',
            'password' => bcrypt('password'),
            'role' => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        // Akun Kas & Bank
        $this->kasAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->bankAccount = ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Bank BCA Operasional',
            'type' => 'asset',
            'is_active' => true,
        ]);

        // Akun Piutang & Hutang
        $this->arAccount = ChartOfAccount::create([
            'code' => '1130',
            'name' => 'Piutang Usaha',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->apAccount = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'Hutang Dagang',
            'type' => 'liability',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'PT Distribusi Pangan Jaya',
            'contact_person' => 'Budi Santoso',
            'phone' => '08198765432',
            'address' => 'Kawasan Industri Rancaekek',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_payment_routes(): void
    {
        $this->get(route('backoffice.payments.index'))->assertRedirect(route('login'));
        $this->get(route('backoffice.payments.receivables.create'))->assertRedirect(route('login'));
        $this->post(route('backoffice.payments.receivables.store'), [])->assertRedirect(route('login'));
        $this->get(route('backoffice.payments.payables.create'))->assertRedirect(route('login'));
        $this->post(route('backoffice.payments.payables.store'), [])->assertRedirect(route('login'));
    }

    public function test_user_can_view_payment_pages(): void
    {
        $this->actingAs($this->user);

        $this->get(route('backoffice.payments.index'))
            ->assertOk()
            ->assertSee('Riwayat Pembayaran AR/AP', false);

        $this->get(route('backoffice.payments.receivables.create'))
            ->assertOk()
            ->assertSee('Penerimaan Pembayaran Piutang', false)
            ->assertSee('Kas Toko');

        $this->get(route('backoffice.payments.payables.create'))
            ->assertOk()
            ->assertSee('Pelunasan Pembayaran Hutang', false)
            ->assertSee('PT Distribusi Pangan Jaya');
    }

    public function test_receivable_payment_form_lists_debtor_name_date_and_description(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bu Tini Debitur',
            'phone' => '0812000111',
        ]);

        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'INV-DEBITUR-01',
            'total_amount' => 250000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $saleDate = $sale->created_at->timezone(config('app.timezone'))->format('d/m/Y');

        $response = $this->get(route('backoffice.payments.receivables.create'));

        $response->assertOk()
            ->assertSee('Nama Debitur', false)
            ->assertSee('Tanggal', false)
            ->assertSee('Keterangan Piutang', false)
            ->assertSee('Bu Tini Debitur', false)
            ->assertSee('INV-DEBITUR-01', false)
            ->assertSee('Piutang penjualan', false);

        $this->assertStringContainsString($saleDate, str_replace('\\', '', $response->getContent()));
    }

    public function test_receivable_payment_form_provides_customer_smart_search_data(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pak Joko Pelanggan',
            'phone' => '0813555777',
        ]);

        Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'INV-JOKO-01',
            'total_amount' => 150000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $content = str_replace('\\', '', $this->get(route('backoffice.payments.receivables.create'))
            ->assertOk()
            ->assertSee('Cari Nama Debitur', false)
            ->assertSee('id="customer_search"', false)
            ->getContent());

        $this->assertStringContainsString('u0022customer_idu0022:'.$customer->id, $content);
        $this->assertStringContainsString('u0022customer_phoneu0022:u00220813555777u0022', $content);
    }

    public function test_debtor_search_reaches_debtors_outside_latest_fifty_invoices(): void
    {
        $this->actingAs($this->user);

        $oldDebtor = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bu Sri Lama',
            'phone' => '0815999000',
        ]);
        $this->createReceivable('INV-SRI-OLD-01', 300000, 100000, 'PARTIAL', $oldDebtor->id);
        $this->createReceivable('INV-SRI-OLD-02', 200000, 0, 'UNPAID', $oldDebtor->id);
        $this->createReceivable('INV-SRI-LUNAS', 500000, 500000, 'PAID', $oldDebtor->id);

        $busyDebtor = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Toko Ramai',
        ]);
        for ($i = 1; $i <= 55; $i++) {
            $this->createReceivable(sprintf('INV-RAMAI-%03d', $i), 10000, 0, 'UNPAID', $busyDebtor->id);
        }

        $this->get(route('backoffice.payments.receivables.create'))
            ->assertOk()
            ->assertDontSee('INV-SRI-OLD-01', false);

        $this->getJson(route('backoffice.payments.receivables.debtors', ['q' => 'sri']))
            ->assertOk()
            ->assertJsonCount(1, 'debtors')
            ->assertJsonPath('debtors.0.customer_id', $oldDebtor->id)
            ->assertJsonPath('debtors.0.name', 'Bu Sri Lama')
            ->assertJsonPath('debtors.0.invoice_count', 2)
            ->assertJsonPath('debtors.0.remaining', 400000);

        $this->getJson(route('backoffice.payments.receivables.debtors', ['q' => 'INV-SRI-OLD-02']))
            ->assertOk()
            ->assertJsonPath('debtors.0.customer_id', $oldDebtor->id)
            ->assertJsonPath('debtors.0.invoice_count', 2);

        $sales = $this->getJson(route('backoffice.payments.receivables.debtor-sales', ['customer' => $oldDebtor->id]))
            ->assertOk()
            ->assertJsonCount(2, 'sales')
            ->json('sales');

        $this->assertEqualsCanonicalizing(
            ['INV-SRI-OLD-01', 'INV-SRI-OLD-02'],
            array_column($sales, 'receipt_number')
        );

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('sales', [
            'receipt_number' => 'INV-SRI-OLD-01',
            'paid_amount' => 100000,
            'payment_status' => 'PARTIAL',
        ]);
    }

    public function test_debtor_search_includes_walk_in_receivables(): void
    {
        $this->actingAs($this->user);

        $this->createReceivable('INV-UMUM-01', 75000, 0, 'UNPAID');

        $this->getJson(route('backoffice.payments.receivables.debtors', ['q' => 'INV-UMUM']))
            ->assertOk()
            ->assertJsonPath('debtors.0.customer_id', null)
            ->assertJsonPath('debtors.0.name', 'Pelanggan Umum');

        $this->getJson(route('backoffice.payments.receivables.debtor-sales', ['customer' => 'walk-in']))
            ->assertOk()
            ->assertJsonPath('sales.0.receipt_number', 'INV-UMUM-01');

        $this->getJson(route('backoffice.payments.receivables.debtor-sales', ['customer' => 'abc']))
            ->assertStatus(422);
    }

    public function test_debtor_search_respects_branch_scope(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Cabang Lain',
            'code' => 'LAIN-01',
            'address' => 'Jl. Lain',
            'phone' => '0800',
        ]);
        $otherCustomer = Customer::create([
            'branch_id' => $otherBranch->id,
            'name' => 'Debitur Cabang Lain',
        ]);
        Sale::create([
            'branch_id' => $otherBranch->id,
            'customer_id' => $otherCustomer->id,
            'receipt_number' => 'INV-LAIN-01',
            'total_amount' => 90000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'tempo',
            'status' => 'completed',
        ]);

        $this->actingAs($this->user);

        $this->getJson(route('backoffice.payments.receivables.debtors', ['q' => 'cabang lain']))
            ->assertOk()
            ->assertJsonCount(0, 'debtors');

        $this->getJson(route('backoffice.payments.receivables.debtor-sales', ['customer' => $otherCustomer->id]))
            ->assertOk()
            ->assertJsonCount(0, 'sales');
    }

    public function test_guest_cannot_access_debtor_search_endpoints(): void
    {
        $this->get(route('backoffice.payments.receivables.debtors', ['q' => 'a']))->assertRedirect(route('login'));
        $this->get(route('backoffice.payments.receivables.debtor-sales', ['customer' => 1]))->assertRedirect(route('login'));
    }

    private function createReceivable(string $receiptNumber, float $total, float $paid, string $status, ?int $customerId = null): Sale
    {
        return Sale::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customerId,
            'receipt_number' => $receiptNumber,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'payment_status' => $status,
            'payment_method' => 'tempo',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Skenario 1: Bayar cicilan Piutang (AR).
     * Pastikan status sales berubah menjadi 'PARTIAL' dan jurnal terbuat seimbang.
     */
    public function test_user_can_pay_partial_receivable_ar(): void
    {
        $this->actingAs($this->user);

        // Buat faktur penjualan bernilai Rp1.000.000 dengan status UNPAID
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-TEST-AR-001',
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('UNPAID', $sale->payment_status);
        $this->assertEquals(0, (float) $sale->paid_amount);

        // Bayar cicilan pertama senilai Rp400.000 via Bank BCA
        $paymentData = [
            'account_id' => $this->bankAccount->id,
            'amount' => 400000,
            'payment_date' => '2026-09-24',
            'reference_number' => 'AR-PAY-TEST-01',
            'notes' => 'Cicilan 1 faktur INV-TEST-AR-001',
            'allocations' => [
                [
                    'sale_id' => $sale->id,
                    'allocated_amount' => 400000,
                ],
            ],
        ];

        $response = $this->post(route('backoffice.payments.receivables.store'), $paymentData);

        $response->assertRedirect(route('backoffice.payments.index'))
            ->assertSessionHas('success');

        // 1. Verifikasi rekaman payments & allocations
        $this->assertDatabaseHas('payments', [
            'branch_id' => $this->branch->id,
            'type' => 'AR',
            'account_id' => $this->bankAccount->id,
            'amount' => 400000.0000,
            'reference_number' => 'AR-PAY-TEST-01',
        ]);

        $payment = Payment::where('reference_number', 'AR-PAY-TEST-01')->first();
        $this->assertNotNull($payment);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'sale_id' => $sale->id,
            'allocated_amount' => 400000.0000,
        ]);

        // 2. Verifikasi status sales berubah menjadi PARTIAL
        $sale->refresh();
        $this->assertEquals(400000.0000, (float) $sale->paid_amount);
        $this->assertEquals('PARTIAL', $sale->payment_status);

        // 3. Verifikasi Jurnal Akuntansi terbuat seimbang
        $this->assertNotNull($payment->journal_header_id);
        $journal = JournalHeader::with('journalLines')->find($payment->journal_header_id);
        $this->assertNotNull($journal);

        $debitBank = $journal->journalLines->firstWhere('chart_of_account_id', $this->bankAccount->id);
        $creditAR = $journal->journalLines->firstWhere('chart_of_account_id', $this->arAccount->id);

        $this->assertNotNull($debitBank, 'Baris Debit Bank BCA harus ada.');
        $this->assertNotNull($creditAR, 'Baris Kredit Piutang Usaha harus ada.');

        $this->assertEquals(400000.00, (float) $debitBank->debit);
        $this->assertEquals(0.00, (float) $debitBank->credit);

        $this->assertEquals(0.00, (float) $creditAR->debit);
        $this->assertEquals(400000.00, (float) $creditAR->credit);

        // Seimbang Debit == Kredit
        $this->assertEquals(400000.00, (float) $journal->journalLines->sum('debit'));
        $this->assertEquals(400000.00, (float) $journal->journalLines->sum('credit'));
    }

    /**
     * Skenario 2: Bayar penuh Hutang (AP).
     * Pastikan status purchase_orders menjadi 'PAID' dan jurnal terbuat.
     */
    public function test_user_can_pay_full_payable_ap(): void
    {
        $this->actingAs($this->user);

        // Buat Purchase Order bernilai Rp2.500.000
        $po = PurchaseOrder::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'PO-TEST-AP-001',
            'order_date' => '2026-09-20',
            'status' => 'completed',
            'total_amount' => 2500000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'notes' => 'Order beras super',
        ]);

        $this->assertEquals('UNPAID', $po->payment_status);

        // Pelunasan penuh Rp2.500.000 dari Kas Toko
        $paymentData = [
            'supplier_id' => $this->supplier->id,
            'account_id' => $this->kasAccount->id,
            'amount' => 2500000,
            'payment_date' => '2026-09-24',
            'reference_number' => 'AP-PAY-FULL-01',
            'notes' => 'Pelunasan penuh PO-TEST-AP-001',
            'allocations' => [
                [
                    'purchase_order_id' => $po->id,
                    'allocated_amount' => 2500000,
                ],
            ],
        ];

        $response = $this->post(route('backoffice.payments.payables.store'), $paymentData);

        $response->assertRedirect(route('backoffice.payments.index'))
            ->assertSessionHas('success');

        // 1. Verifikasi rekaman payments
        $this->assertDatabaseHas('payments', [
            'branch_id' => $this->branch->id,
            'type' => 'AP',
            'supplier_id' => $this->supplier->id,
            'account_id' => $this->kasAccount->id,
            'amount' => 2500000.0000,
            'reference_number' => 'AP-PAY-FULL-01',
        ]);

        $payment = Payment::where('reference_number', 'AP-PAY-FULL-01')->first();
        $this->assertNotNull($payment);

        // 2. Verifikasi status purchase_orders menjadi PAID
        $po->refresh();
        $this->assertEquals(2500000.0000, (float) $po->paid_amount);
        $this->assertEquals('PAID', $po->payment_status);

        // 3. Verifikasi Jurnal Akuntansi: Debit Hutang Usaha, Kredit Kas Toko
        $this->assertNotNull($payment->journal_header_id);
        $journal = JournalHeader::with('journalLines')->find($payment->journal_header_id);

        $debitAP = $journal->journalLines->firstWhere('chart_of_account_id', $this->apAccount->id);
        $creditKas = $journal->journalLines->firstWhere('chart_of_account_id', $this->kasAccount->id);

        $this->assertNotNull($debitAP, 'Baris Debit Hutang Usaha harus ada.');
        $this->assertNotNull($creditKas, 'Baris Kredit Kas Toko harus ada.');

        $this->assertEquals(2500000.00, (float) $debitAP->debit);
        $this->assertEquals(0.00, (float) $debitAP->credit);

        $this->assertEquals(0.00, (float) $creditKas->debit);
        $this->assertEquals(2500000.00, (float) $creditKas->credit);

        // Seimbang
        $this->assertEquals(2500000.00, (float) $journal->journalLines->sum('debit'));
        $this->assertEquals(2500000.00, (float) $journal->journalLines->sum('credit'));
    }

    /**
     * Skenario 3: Alokasi Multi-Faktur Sekaligus (Multi-Invoice Allocation).
     */
    public function test_multi_invoice_allocation_for_receivable(): void
    {
        $service = app(ARAPPaymentService::class);

        $sale1 = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-MULTI-01',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status' => 'completed',
        ]);

        $sale2 = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-MULTI-02',
            'total_amount' => 800000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status' => 'completed',
        ]);

        // Bayar total Rp900.000: Rp500.000 untuk sale1 (lunas) dan Rp400.000 untuk sale2 (parsial)
        $payment = $service->processARPayment([
            'branch_id' => $this->branch->id,
            'account_id' => $this->bankAccount->id,
            'amount' => 900000,
            'payment_date' => '2026-09-24',
            'reference_number' => 'AR-MULTI-TEST',
            'notes' => 'Multi-invoice payment',
        ], [
            ['sale_id' => $sale1->id, 'allocated_amount' => 500000],
            ['sale_id' => $sale2->id, 'allocated_amount' => 400000],
        ], $this->user);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertCount(2, $payment->allocations);

        $sale1->refresh();
        $sale2->refresh();

        $this->assertEquals(500000.0000, (float) $sale1->paid_amount);
        $this->assertEquals('PAID', $sale1->payment_status);

        $this->assertEquals(400000.0000, (float) $sale2->paid_amount);
        $this->assertEquals('PARTIAL', $sale2->payment_status);
    }

    public function test_payment_validation_fails_for_invalid_amount(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.payments.receivables.store'), [
            'account_id' => $this->bankAccount->id,
            'amount' => 0, // Nilai 0
            'payment_date' => '2026-09-24',
            'allocations' => [],
        ]);

        $response->assertSessionHasErrors(['amount', 'allocations']);
        $this->assertDatabaseCount('payments', 0);
    }
}
