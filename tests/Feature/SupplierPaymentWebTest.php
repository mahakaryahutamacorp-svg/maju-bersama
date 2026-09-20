<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private Supplier $supplier;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $payableAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'      => 'Gudang Pusat',
            'code'      => 'PUSAT',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role'      => 'master',
        ]);

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id'      => $this->branch->id,
            'name'           => 'PT Pupuk Nusantara',
            'contact_person' => 'Budi Santoso',
            'phone'          => '081234567890',
            'is_active'      => true,
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Operasional',
            'type' => 'asset',
        ]);

        $this->payableAccount = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'Hutang Dagang',
            'type' => 'liability',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/backoffice/supplier-payments')->assertRedirect('/login');
        $this->get('/backoffice/supplier-payments/create')->assertRedirect('/login');
    }

    public function test_user_can_view_index_and_create_pages(): void
    {
        $indexResponse = $this->actingAs($this->user)->get('/backoffice/supplier-payments');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Riwayat Pembayaran Supplier');
        $indexResponse->assertSee('Total Pembayaran');

        $createResponse = $this->actingAs($this->user)->get('/backoffice/supplier-payments/create');
        $createResponse->assertOk();
        $createResponse->assertSee('Formulir Pembayaran Supplier');
        $createResponse->assertSee('PT Pupuk Nusantara');
        $createResponse->assertSee('1110 - Kas Operasional');
        $createResponse->assertSee('paymentForm');
    }

    public function test_user_can_store_supplier_payment_with_journal_entry(): void
    {
        $payload = [
            'supplier_id'         => $this->supplier->id,
            'chart_of_account_id' => $this->cashAccount->id,
            'payment_date'        => now()->toDateString(),
            'payment_method'      => 'Transfer',
            'amount'              => 2500000,
            'reference_number'    => 'TRF-PAY-001',
            'notes'               => 'Pelunasan invoice PO bahan pupuk',
        ];

        $postResponse = $this->actingAs($this->user)->post('/backoffice/supplier-payments', $payload);

        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect(route('backoffice.supplier-payments.index'));

        // Assert record created in supplier_payments
        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id'         => $this->supplier->id,
            'chart_of_account_id' => $this->cashAccount->id,
            'payment_method'      => 'Transfer',
            'amount'              => '2500000.00',
            'reference_number'    => 'TRF-PAY-001',
        ]);

        $payment = SupplierPayment::first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->journal_header_id);

        // Assert journal header & balanced lines created
        $journal = JournalHeader::with('journalLines')->find($payment->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertCount(2, $journal->journalLines);

        // Line 1: Dr Hutang Dagang 2110
        $debitLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->payableAccount->id);
        $this->assertNotNull($debitLine);
        $this->assertEquals(2500000.00, (float) $debitLine->debit);
        $this->assertEquals(0, (float) $debitLine->credit);

        // Line 2: Cr Kas 1110
        $creditLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->cashAccount->id);
        $this->assertNotNull($creditLine);
        $this->assertEquals(0, (float) $creditLine->debit);
        $this->assertEquals(2500000.00, (float) $creditLine->credit);
    }

    public function test_store_validation_fails_for_invalid_input(): void
    {
        $response = $this->actingAs($this->user)->post('/backoffice/supplier-payments', [
            'supplier_id'         => 99999, // non-existent
            'chart_of_account_id' => 99999,
            'payment_date'        => 'not-a-date',
            'payment_method'      => 'Bitcoin', // invalid enum
            'amount'              => -500, // negative
        ]);

        $response->assertSessionHasErrors([
            'supplier_id',
            'chart_of_account_id',
            'payment_date',
            'payment_method',
            'amount',
        ]);
    }
}
