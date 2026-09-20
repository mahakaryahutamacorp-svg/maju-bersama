<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\CashTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashTransferWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $bankAccount;

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

        $this->cashAccount = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Toko Utama',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->bankAccount = ChartOfAccount::create([
            'code'      => '1120',
            'name'      => 'Rekening Bank BCA',
            'type'      => 'asset',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('backoffice.cash-transfers.index'))
            ->assertRedirect(route('login'));

        $this->get(route('backoffice.cash-transfers.create'))
            ->assertRedirect(route('login'));

        $this->post(route('backoffice.cash-transfers.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_cash_transfers_index_and_create_pages(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('backoffice.cash-transfers.index'));
        $response->assertOk()
            ->assertSee('Mutasi Kas &amp; Bank', false)
            ->assertSee('Riwayat Mutasi Kas &amp; Bank', false);

        $createResponse = $this->get(route('backoffice.cash-transfers.create'));
        $createResponse->assertOk()
            ->assertSee('Transfer Antar Kas &amp; Bank', false)
            ->assertSee('Kas Toko Utama')
            ->assertSee('Rekening Bank BCA');
    }

    public function test_user_can_store_cash_transfer_with_automatic_journal(): void
    {
        $this->actingAs($this->user);

        $data = [
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->bankAccount->id,
            'amount'           => 1250000,
            'transfer_date'    => '2026-09-20',
            'reference_number' => 'TRF-TEST-WEB-01',
            'notes'            => 'Setoran kas operasional harian ke BCA',
        ];

        $response = $this->post(route('backoffice.cash-transfers.store'), $data);

        $response->assertRedirect(route('backoffice.cash-transfers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cash_transfers', [
            'branch_id'        => $this->branch->id,
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->bankAccount->id,
            'amount'           => 1250000.00,
            'reference_number' => 'TRF-TEST-WEB-01',
            'notes'            => 'Setoran kas operasional harian ke BCA',
        ]);

        $transfer = CashTransfer::where('reference_number', 'TRF-TEST-WEB-01')->first();
        $this->assertNotNull($transfer);
        $this->assertNotNull($transfer->journal_header_id);

        $journal = JournalHeader::with('journalLines')->find($transfer->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertEquals(
            'Mutasi Kas: dari Kas Toko Utama ke Rekening Bank BCA - Setoran kas operasional harian ke BCA',
            $journal->description
        );

        $debitLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->bankAccount->id);
        $creditLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->cashAccount->id);

        $this->assertEquals(1250000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(1250000.00, (float) $creditLine->credit);
    }

    public function test_store_validation_fails_when_same_account_selected(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.cash-transfers.store'), [
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->cashAccount->id, // Sama persis
            'amount'          => 500000,
            'transfer_date'   => '2026-09-20',
        ]);

        $response->assertSessionHasErrors(['to_account_id']);
        $this->assertDatabaseCount('cash_transfers', 0);
    }

    public function test_store_validation_fails_for_non_positive_amount(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.cash-transfers.store'), [
            'from_account_id' => $this->cashAccount->id,
            'to_account_id'   => $this->bankAccount->id,
            'amount'          => 0,
            'transfer_date'   => '2026-09-20',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertDatabaseCount('cash_transfers', 0);
    }

    public function test_user_can_view_cash_transfer_voucher_detail(): void
    {
        $this->actingAs($this->user);

        $service = app(CashTransferService::class);
        $transfer = $service->processTransfer([
            'branch_id'        => $this->branch->id,
            'from_account_id'  => $this->cashAccount->id,
            'to_account_id'    => $this->bankAccount->id,
            'amount'           => 350000,
            'transfer_date'    => '2026-09-20',
            'reference_number' => 'TRF-VOUCHER-01',
            'notes'            => 'Uji tampilan voucher',
        ]);

        $response = $this->get(route('backoffice.cash-transfers.show', $transfer->id));
        $response->assertOk()
            ->assertSee('Voucher Mutasi Kas &amp; Bank', false)
            ->assertSee('TRF-VOUCHER-01')
            ->assertSee('350.000')
            ->assertSee('Kas Toko Utama')
            ->assertSee('Rekening Bank BCA')
            ->assertSee('Uji tampilan voucher');
    }
}
