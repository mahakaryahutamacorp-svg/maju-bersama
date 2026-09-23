<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
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
            'name'    => 'Cabang Utama Bandung',
            'code'    => 'BDG-01',
            'address' => 'Jl. Asia Afrika No. 10',
            'phone'   => '0812345678',
        ]);

        $this->user = User::create([
            'name'      => 'Finance Officer',
            'email'     => 'finance@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        // Akun Kas & Bank
        $this->kasAccount = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Toko',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->bankAccount = ChartOfAccount::create([
            'code'      => '1120',
            'name'      => 'Bank BCA Operasional',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        // Akun Piutang & Hutang
        $this->arAccount = ChartOfAccount::create([
            'code'      => '1130',
            'name'      => 'Piutang Usaha',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->apAccount = ChartOfAccount::create([
            'code'      => '2110',
            'name'      => 'Hutang Dagang',
            'type'      => 'liability',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'branch_id'      => $this->branch->id,
            'name'           => 'PT Distribusi Pangan Jaya',
            'contact_person' => 'Budi Santoso',
            'phone'          => '08198765432',
            'address'        => 'Kawasan Industri Rancaekek',
            'is_active'      => true,
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

    /**
     * Skenario 1: Bayar cicilan Piutang (AR).
     * Pastikan status sales berubah menjadi 'PARTIAL' dan jurnal terbuat seimbang.
     */
    public function test_user_can_pay_partial_receivable_ar(): void
    {
        $this->actingAs($this->user);

        // Buat faktur penjualan bernilai Rp1.000.000 dengan status UNPAID
        $sale = Sale::create([
            'branch_id'      => $this->branch->id,
            'receipt_number' => 'INV-TEST-AR-001',
            'total_amount'   => 1000000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status'         => 'completed',
            'created_by'     => $this->user->id,
        ]);

        $this->assertEquals('UNPAID', $sale->payment_status);
        $this->assertEquals(0, (float) $sale->paid_amount);

        // Bayar cicilan pertama senilai Rp400.000 via Bank BCA
        $paymentData = [
            'account_id'       => $this->bankAccount->id,
            'amount'           => 400000,
            'payment_date'     => '2026-09-24',
            'reference_number' => 'AR-PAY-TEST-01',
            'notes'            => 'Cicilan 1 faktur INV-TEST-AR-001',
            'allocations'      => [
                [
                    'sale_id'          => $sale->id,
                    'allocated_amount' => 400000,
                ],
            ],
        ];

        $response = $this->post(route('backoffice.payments.receivables.store'), $paymentData);

        $response->assertRedirect(route('backoffice.payments.index'))
            ->assertSessionHas('success');

        // 1. Verifikasi rekaman payments & allocations
        $this->assertDatabaseHas('payments', [
            'branch_id'        => $this->branch->id,
            'type'             => 'AR',
            'account_id'       => $this->bankAccount->id,
            'amount'           => 400000.0000,
            'reference_number' => 'AR-PAY-TEST-01',
        ]);

        $payment = Payment::where('reference_number', 'AR-PAY-TEST-01')->first();
        $this->assertNotNull($payment);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id'       => $payment->id,
            'sale_id'          => $sale->id,
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
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $this->supplier->id,
            'reference_number' => 'PO-TEST-AP-001',
            'order_date'       => '2026-09-20',
            'status'           => 'completed',
            'total_amount'     => 2500000,
            'paid_amount'      => 0,
            'payment_status'   => 'UNPAID',
            'notes'            => 'Order beras super',
        ]);

        $this->assertEquals('UNPAID', $po->payment_status);

        // Pelunasan penuh Rp2.500.000 dari Kas Toko
        $paymentData = [
            'supplier_id'      => $this->supplier->id,
            'account_id'       => $this->kasAccount->id,
            'amount'           => 2500000,
            'payment_date'     => '2026-09-24',
            'reference_number' => 'AP-PAY-FULL-01',
            'notes'            => 'Pelunasan penuh PO-TEST-AP-001',
            'allocations'      => [
                [
                    'purchase_order_id' => $po->id,
                    'allocated_amount'  => 2500000,
                ],
            ],
        ];

        $response = $this->post(route('backoffice.payments.payables.store'), $paymentData);

        $response->assertRedirect(route('backoffice.payments.index'))
            ->assertSessionHas('success');

        // 1. Verifikasi rekaman payments
        $this->assertDatabaseHas('payments', [
            'branch_id'        => $this->branch->id,
            'type'             => 'AP',
            'supplier_id'      => $this->supplier->id,
            'account_id'       => $this->kasAccount->id,
            'amount'           => 2500000.0000,
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
            'branch_id'      => $this->branch->id,
            'receipt_number' => 'INV-MULTI-01',
            'total_amount'   => 500000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status'         => 'completed',
        ]);

        $sale2 = Sale::create([
            'branch_id'      => $this->branch->id,
            'receipt_number' => 'INV-MULTI-02',
            'total_amount'   => 800000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
            'payment_method' => 'credit',
            'status'         => 'completed',
        ]);

        // Bayar total Rp900.000: Rp500.000 untuk sale1 (lunas) dan Rp400.000 untuk sale2 (parsial)
        $payment = $service->processARPayment([
            'branch_id'        => $this->branch->id,
            'account_id'       => $this->bankAccount->id,
            'amount'           => 900000,
            'payment_date'     => '2026-09-24',
            'reference_number' => 'AR-MULTI-TEST',
            'notes'            => 'Multi-invoice payment',
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
            'account_id'   => $this->bankAccount->id,
            'amount'       => 0, // Nilai 0
            'payment_date' => '2026-09-24',
            'allocations'  => [],
        ]);

        $response->assertSessionHasErrors(['amount', 'allocations']);
        $this->assertDatabaseCount('payments', 0);
    }
}
