<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\CashTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashTransactionWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private ChartOfAccount $kasTunai;
    private ChartOfAccount $bankBca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'    => 'Cabang Jakarta Pusat',
            'code'    => 'JKT-01',
            'address' => 'Jl. Thamrin No. 1',
            'phone'   => '0811001122',
        ]);

        $this->user = User::create([
            'name'      => 'Admin Kasir JKT',
            'email'     => 'kasir.jkt@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        // Kas Tunai / Laci Kasir (1110)
        $this->kasTunai = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Tunai Laci Kasir',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        // Rekening Bank BCA (1120)
        $this->bankBca = ChartOfAccount::create([
            'code'      => '1120',
            'name'      => 'Rekening Bank BCA',
            'type'      => 'asset',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_cash_transactions(): void
    {
        $this->get(route('backoffice.cash-transactions.create'))
            ->assertRedirect(route('login'));

        $this->post(route('backoffice.cash-transactions.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_cash_transaction_create_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('backoffice.cash-transactions.create'));
        $response->assertOk()
            ->assertSee('Formulir Transaksi &amp; Mutasi Kas', false)
            ->assertSee('Transfer Kas (Mutasi)')
            ->assertSee('Akun Sumber (Dari)')
            ->assertSee('Akun Tujuan (Ke)')
            ->assertSee('Nominal Transfer (Rp)')
            ->assertSee('Kas Tunai Laci Kasir')
            ->assertSee('Rekening Bank BCA');
    }

    /**
     * Skenario Khusus Simulasi Mutasi Kas:
     * Transfer Rp1.000.000 dari Kas Tunai (Laci Kasir) ke Bank BCA.
     * Verifikasi total Debit dan Kredit di Jurnal seimbang dan uang benar-benar berpindah.
     */
    public function test_user_can_simulate_cash_transfer_mutation_from_cash_to_bank(): void
    {
        $this->actingAs($this->user);

        $transferData = [
            'type'             => 'TRANSFER',
            'from_account_id'  => $this->kasTunai->id,
            'to_account_id'    => $this->bankBca->id,
            'amount'           => 1000000,
            'transaction_date' => '2026-09-24',
            'reference_number' => 'TRF-TEST-1M-01',
            'notes'            => 'Setoran kas laci kasir ke rekening Bank BCA operasional',
        ];

        $response = $this->post(route('backoffice.cash-transactions.store'), $transferData);

        // 1. Verifikasi redirect & session flash message
        $response->assertRedirect(route('backoffice.cash-transfers.index'))
            ->assertSessionHas('success');

        // 2. Verifikasi data tersimpan di tabel cash_transfers
        $this->assertDatabaseHas('cash_transfers', [
            'branch_id'        => $this->branch->id,
            'from_account_id'  => $this->kasTunai->id,
            'to_account_id'    => $this->bankBca->id,
            'amount'           => 1000000.00,
            'reference_number' => 'TRF-TEST-1M-01',
        ]);

        $transfer = CashTransfer::where('reference_number', 'TRF-TEST-1M-01')->first();
        $this->assertNotNull($transfer);
        $this->assertNotNull($transfer->journal_header_id);

        // 3. Verifikasi Jurnal Akuntansi dibuat dan terhubung
        $journal = JournalHeader::with('journalLines')->find($transfer->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertEquals(
            'Mutasi Kas: dari Kas Tunai Laci Kasir ke Rekening Bank BCA - Setoran kas laci kasir ke rekening Bank BCA operasional',
            $journal->description
        );

        $lines = $journal->journalLines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('chart_of_account_id', $this->bankBca->id);
        $creditLine = $lines->firstWhere('chart_of_account_id', $this->kasTunai->id);

        $this->assertNotNull($debitLine, 'Baris Debit ke Akun Tujuan Bank BCA harus ada.');
        $this->assertNotNull($creditLine, 'Baris Kredit ke Akun Sumber Kas Tunai harus ada.');

        // 4. Verifikasi uang benar-benar berpindah:
        // Bank BCA (Tujuan) bertambah di sisi DEBIT senilai Rp1.000.000
        $this->assertEquals(1000000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        // Kas Tunai (Sumber) berkurang di sisi KREDIT senilai Rp1.000.000
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(1000000.00, (float) $creditLine->credit);

        // 5. Verifikasi total Debit dan Kredit di Jurnal seimbang (Balanced)
        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEquals(1000000.00, $totalDebit);
        $this->assertEquals(1000000.00, $totalCredit);
        $this->assertEquals($totalDebit, $totalCredit, 'Jurnal mutasi kas harus seimbang (Total Debit == Total Kredit).');
    }

    public function test_transfer_validation_fails_when_same_account_selected(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.cash-transactions.store'), [
            'type'             => 'TRANSFER',
            'from_account_id'  => $this->kasTunai->id,
            'to_account_id'    => $this->kasTunai->id, // Sama persis
            'amount'           => 500000,
            'transaction_date' => '2026-09-24',
        ]);

        $response->assertSessionHasErrors(['to_account_id']);
        $this->assertDatabaseCount('cash_transfers', 0);
    }

    public function test_transfer_validation_fails_for_non_positive_amount(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.cash-transactions.store'), [
            'type'             => 'TRANSFER',
            'from_account_id'  => $this->kasTunai->id,
            'to_account_id'    => $this->bankBca->id,
            'amount'           => 0, // Nilai 0
            'transaction_date' => '2026-09-24',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertDatabaseCount('cash_transfers', 0);
    }

    public function test_cash_transaction_service_methods_directly(): void
    {
        $service = app(CashTransactionService::class);

        // Uji metode storeTransfer langsung
        $transfer = $service->storeTransfer([
            'branch_id'        => $this->branch->id,
            'user_id'          => $this->user->id,
            'from_account_id'  => $this->bankBca->id,
            'to_account_id'    => $this->kasTunai->id,
            'amount'           => 500000,
            'transaction_date' => '2026-09-24',
            'reference_number' => 'TRF-SVC-TEST-01',
            'notes'            => 'Tarik tunai dari Bank BCA untuk kas kecil',
        ]);

        $this->assertInstanceOf(CashTransfer::class, $transfer);
        $this->assertEquals(500000.00, (float) $transfer->amount);
        $this->assertNotNull($transfer->journal_header_id);

        $journal = JournalHeader::with('journalLines')->find($transfer->journal_header_id);
        $debitKas = $journal->journalLines->firstWhere('chart_of_account_id', $this->kasTunai->id);
        $creditBank = $journal->journalLines->firstWhere('chart_of_account_id', $this->bankBca->id);

        $this->assertEquals(500000.00, (float) $debitKas->debit);
        $this->assertEquals(500000.00, (float) $creditBank->credit);

        // Uji metode storeTransaction dengan type == 'TRANSFER'
        $transfer2 = $service->storeTransaction([
            'type'             => 'TRANSFER',
            'branch_id'        => $this->branch->id,
            'user_id'          => $this->user->id,
            'from_account_id'  => $this->kasTunai->id,
            'to_account_id'    => $this->bankBca->id,
            'amount'           => 250000,
            'transaction_date' => '2026-09-24',
            'reference_number' => 'TRF-SVC-TEST-02',
            'notes'            => 'Penyetoran kas via storeTransaction',
        ]);

        $this->assertInstanceOf(CashTransfer::class, $transfer2);
        $this->assertEquals(250000.00, (float) $transfer2->amount);
    }
}
