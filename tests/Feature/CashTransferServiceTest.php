<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\CashTransferService;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;
    private Branch $branch2;
    private User $branch1User;
    private User $masterUser;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $bankAccount;
    private ChartOfAccount $pettyCashAccount;
    private CashTransferService $cashTransferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashTransferService = app(CashTransferService::class);

        $this->branch1 = Branch::create([
            'name'    => 'Cabang Jakarta Pusat',
            'code'    => 'JKT-01',
            'address' => 'Jl. Thamrin No. 1',
            'phone'   => '0811001122',
        ]);

        $this->branch2 = Branch::create([
            'name'    => 'Cabang Surabaya Barat',
            'code'    => 'SBY-01',
            'address' => 'Jl. HR Muhammad No. 5',
            'phone'   => '0822003344',
        ]);

        $this->branch1User = User::create([
            'name'      => 'Admin Cabang JKT',
            'email'     => 'admin.jkt@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch1->id,
        ]);

        $this->masterUser = User::create([
            'name'      => 'Owner Pusat',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'master',
            'branch_id' => $this->branch1->id,
        ]);

        // COA Kas Utama
        $this->cashAccount = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Toko Utama',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        // COA Rekening Bank BCA
        $this->bankAccount = ChartOfAccount::create([
            'code'      => '1120',
            'name'      => 'Bank BCA Operasional',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        // COA Kas Kecil (Petty Cash)
        $this->pettyCashAccount = ChartOfAccount::create([
            'code'      => '1115',
            'name'      => 'Kas Kecil Cabang',
            'type'      => 'asset',
            'is_active' => true,
        ]);
    }

    public function test_can_successfully_transfer_funds_and_create_balanced_journal(): void
    {
        $this->actingAs($this->branch1User);

        $data = [
            'branch_id'       => $this->branch1->id,
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->bankAccount->id,
            'amount'          => 750000,
            'transfer_date'   => '2026-09-20',
            'notes'           => 'Setoran kas operasional ke rekening BCA',
        ];

        $transfer = $this->cashTransferService->processTransfer($data);

        // 1. Verifikasi rekaman cash_transfers
        $this->assertInstanceOf(CashTransfer::class, $transfer);
        $this->assertDatabaseHas('cash_transfers', [
            'id'              => $transfer->id,
            'branch_id'       => $this->branch1->id,
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->bankAccount->id,
            'amount'          => 750000.00,
            'notes'           => 'Setoran kas operasional ke rekening BCA',
        ]);
        $this->assertSame('2026-09-20', $transfer->transfer_date->format('Y-m-d'));

        $this->assertStringStartsWith('TRF-', $transfer->reference_number);
        $this->assertNotNull($transfer->journal_header_id);

        // 2. Verifikasi JournalHeader
        $journal = JournalHeader::with('journalLines')->find($transfer->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertEquals($this->branch1->id, $journal->branch_id);
        $this->assertEquals($transfer->reference_number, $journal->reference_number);
        $this->assertEquals(
            'Mutasi Kas: dari Kas Toko Utama ke Bank BCA Operasional - Setoran kas operasional ke rekening BCA',
            $journal->description
        );

        // 3. Verifikasi JournalLines (Debit ke Akun Tujuan, Kredit ke Akun Asal)
        $this->assertCount(2, $journal->journalLines);

        $debitLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->bankAccount->id);
        $this->assertNotNull($debitLine);
        $this->assertEquals(750000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        $creditLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->cashAccount->id);
        $this->assertNotNull($creditLine);
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(750000.00, (float) $creditLine->credit);

        // Verifikasi keseimbangan debit dan kredit
        $totalDebit = $journal->journalLines->sum('debit');
        $totalCredit = $journal->journalLines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
    }

    public function test_rejects_transfer_when_amount_is_zero_or_negative(): void
    {
        $this->actingAs($this->branch1User);

        // Uji amount 0
        try {
            $this->cashTransferService->processTransfer([
                'branch_id'       => $this->branch1->id,
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->bankAccount->id,
                'amount'          => 0,
            ]);
            $this->fail('Expected ValidationException was not thrown for amount 0.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Uji amount negatif
        try {
            $this->cashTransferService->processTransfer([
                'branch_id'       => $this->branch1->id,
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->bankAccount->id,
                'amount'          => -50000,
            ]);
            $this->fail('Expected ValidationException was not thrown for negative amount.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertDatabaseCount('cash_transfers', 0);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_rejects_transfer_when_from_and_to_accounts_are_identical(): void
    {
        $this->actingAs($this->branch1User);

        $this->expectException(ValidationException::class);

        $this->cashTransferService->processTransfer([
            'branch_id'       => $this->branch1->id,
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->cashAccount->id, // Akun sama
            'amount'          => 200000,
        ]);

        $this->assertDatabaseCount('cash_transfers', 0);
    }

    public function test_rejects_transfer_if_account_is_inactive(): void
    {
        $this->actingAs($this->branch1User);

        $inactiveAccount = ChartOfAccount::create([
            'code'      => '1199',
            'name'      => 'Kas Lama Nonaktif',
            'type'      => 'asset',
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->cashTransferService->processTransfer([
            'branch_id'       => $this->branch1->id,
            'from_account_id' => $inactiveAccount->id,
            'to_account_id'   => $this->bankAccount->id,
            'amount'          => 100000,
        ]);
    }

    public function test_custom_reference_number_and_date_are_preserved(): void
    {
        $this->actingAs($this->branch1User);

        $customRef = 'CUSTOM-TRF-999';
        $customDate = '2026-08-15';

        $transfer = $this->cashTransferService->processTransfer([
            'branch_id'        => $this->branch1->id,
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->pettyCashAccount->id,
            'amount'           => 150000,
            'transfer_date'    => $customDate,
            'reference_number' => $customRef,
        ]);

        $this->assertEquals($customRef, $transfer->reference_number);
        $this->assertEquals($customDate, $transfer->transfer_date->toDateString());

        $this->assertDatabaseHas('journal_headers', [
            'id'               => $transfer->journal_header_id,
            'reference_number' => $customRef,
            'description'      => 'Mutasi Kas: dari Kas Toko Utama ke Kas Kecil Cabang',
        ]);
        $this->assertSame($customDate, $transfer->journalHeader->transaction_date->format('Y-m-d'));
    }

    public function test_branch_scope_isolates_records_for_branch_admin_and_permits_master(): void
    {
        // 1. Buat mutasi di Branch 1
        $transferBranch1 = CashTransfer::create([
            'branch_id'        => $this->branch1->id,
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->bankAccount->id,
            'amount'           => 500000,
            'transfer_date'    => '2026-09-20',
            'reference_number' => 'TRF-BR1-001',
        ]);

        // 2. Buat mutasi di Branch 2
        $transferBranch2 = CashTransfer::create([
            'branch_id'        => $this->branch2->id,
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->bankAccount->id,
            'amount'           => 300000,
            'transfer_date'    => '2026-09-20',
            'reference_number' => 'TRF-BR2-002',
        ]);

        // User Branch 1 hanya melihat mutasi miliknya
        $this->actingAs($this->branch1User);
        $scopedTransfers = CashTransfer::all();
        $this->assertTrue($scopedTransfers->contains($transferBranch1));
        $this->assertFalse($scopedTransfers->contains($transferBranch2));

        // Master User dapat melihat seluruh mutasi
        $this->actingAs($this->masterUser);
        $masterTransfers = CashTransfer::all();
        $this->assertTrue($masterTransfers->contains($transferBranch1));
        $this->assertTrue($masterTransfers->contains($transferBranch2));
    }

    public function test_model_relationships(): void
    {
        $this->actingAs($this->branch1User);

        $transfer = $this->cashTransferService->processTransfer([
            'branch_id'       => $this->branch1->id,
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->bankAccount->id,
            'amount'          => 250000,
            'notes'           => 'Uji relasi model',
        ]);

        // Relasi pada CashTransfer
        $this->assertEquals($this->branch1->id, $transfer->branch->id);
        $this->assertEquals($this->cashAccount->id, $transfer->fromAccount->id);
        $this->assertEquals($this->bankAccount->id, $transfer->toAccount->id);
        $this->assertEquals($transfer->journal_header_id, $transfer->journalHeader->id);

        // Relasi pada ChartOfAccount (outgoingTransfers & incomingTransfers)
        $this->assertTrue($this->cashAccount->outgoingTransfers->contains($transfer));
        $this->assertTrue($this->bankAccount->incomingTransfers->contains($transfer));
    }
}
