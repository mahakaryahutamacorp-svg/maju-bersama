<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $bankAccount;
    private ChartOfAccount $inventoryAccount;
    private ChartOfAccount $payableAccount;
    private ChartOfAccount $equityAccount;

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
            'name'      => 'Admin Cabang',
            'email'     => 'admin.jkt@example.com',
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
            'name'      => 'Bank BCA Operasional',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->inventoryAccount = ChartOfAccount::create([
            'code'      => '1210',
            'name'      => 'Persediaan Barang',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->payableAccount = ChartOfAccount::create([
            'code'      => '2110',
            'name'      => 'Hutang Dagang',
            'type'      => 'liability',
            'is_active' => true,
        ]);

        $this->equityAccount = ChartOfAccount::create([
            'code'      => '3110',
            'name'      => 'Modal Awal',
            'type'      => 'equity',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('backoffice.opening-balances.create'))
            ->assertRedirect(route('login'));

        $this->post(route('backoffice.opening-balances.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_opening_balance_create_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('backoffice.opening-balances.create'));
        $response->assertOk()
            ->assertSee('Input Saldo Awal (Opening Balance)', false)
            ->assertSee('Aset (Harta Perusahaan)')
            ->assertSee('Kewajiban (Hutang Perusahaan)')
            ->assertSee('Kalkulasi Otomatis Modal Bersih');
    }

    public function test_user_can_post_opening_balance_with_balanced_double_entry_journal(): void
    {
        $this->actingAs($this->user);

        $data = [
            'transaction_date' => '2026-09-01',
            'reference_number' => 'OB-TEST-001',
            'notes'            => 'Setup saldo awal cabang baru',
            'cash_in_drawer'   => 5000000,   // Kas: 5.000.000
            'bank_bca'         => 20000000,  // Bank: 20.000.000
            'inventory'        => 15000000,  // Persediaan: 15.000.000
            'payable'          => 8000000,   // Hutang: 8.000.000
            // Total Aset = 40.000.000 (Dr)
            // Total Hutang = 8.000.000 (Cr)
            // Modal Bersih / Ekuitas = 32.000.000 (Cr)
        ];

        $response = $this->post(route('backoffice.opening-balances.store'), $data);

        $response->assertRedirect(route('backoffice.opening-balances.create'))
            ->assertSessionHas('success');

        // 1. Verifikasi Header Jurnal
        $journal = JournalHeader::with('journalLines')->where('reference_number', 'OB-TEST-001')->first();
        $this->assertNotNull($journal);
        $this->assertEquals($this->branch->id, $journal->branch_id);
        $this->assertEquals('Setor Saldo Awal Sistem - Setup saldo awal cabang baru', $journal->description);

        // 2. Verifikasi Baris-baris Jurnal
        $this->assertCount(5, $journal->journalLines);

        // Kas (Debit 5jt)
        $cashLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->cashAccount->id);
        $this->assertNotNull($cashLine);
        $this->assertEquals(5000000.00, (float) $cashLine->debit);
        $this->assertEquals(0.00, (float) $cashLine->credit);

        // Bank BCA (Debit 20jt)
        $bankLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->bankAccount->id);
        $this->assertNotNull($bankLine);
        $this->assertEquals(20000000.00, (float) $bankLine->debit);
        $this->assertEquals(0.00, (float) $bankLine->credit);

        // Persediaan (Debit 15jt)
        $invLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->inventoryAccount->id);
        $this->assertNotNull($invLine);
        $this->assertEquals(15000000.00, (float) $invLine->debit);
        $this->assertEquals(0.00, (float) $invLine->credit);

        // Hutang Dagang (Kredit 8jt)
        $payLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->payableAccount->id);
        $this->assertNotNull($payLine);
        $this->assertEquals(0.00, (float) $payLine->debit);
        $this->assertEquals(8000000.00, (float) $payLine->credit);

        // Modal Awal (Kredit 32jt)
        $eqLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->equityAccount->id);
        $this->assertNotNull($eqLine);
        $this->assertEquals(0.00, (float) $eqLine->debit);
        $this->assertEquals(32000000.00, (float) $eqLine->credit);

        // 3. Verifikasi Keseimbangan Jurnal Total Debit == Total Kredit
        $totalDebit = $journal->journalLines->sum('debit');
        $totalCredit = $journal->journalLines->sum('credit');
        $this->assertEquals(40000000.00, (float) $totalDebit);
        $this->assertEquals(40000000.00, (float) $totalCredit);
        $this->assertEquals($totalDebit, $totalCredit);
    }

    public function test_cannot_post_opening_balance_twice_for_same_branch(): void
    {
        $this->actingAs($this->user);

        // Submit pertama kali
        $this->post(route('backoffice.opening-balances.store'), [
            'transaction_date' => '2026-09-01',
            'cash_in_drawer'   => 1000000,
        ])->assertRedirect(route('backoffice.opening-balances.create'));

        // Submit kedua kali (harus gagal validasi duplicate)
        $response = $this->post(route('backoffice.opening-balances.store'), [
            'transaction_date' => '2026-09-01',
            'cash_in_drawer'   => 2000000,
        ]);

        $response->assertSessionHasErrors(['general']);
        $this->assertEquals(1, JournalHeader::where('branch_id', $this->branch->id)->count());
    }

    public function test_cannot_post_when_all_balances_are_zero(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.opening-balances.store'), [
            'transaction_date' => '2026-09-01',
            'cash_in_drawer'   => 0,
            'bank_bca'         => 0,
            'inventory'        => 0,
            'payable'          => 0,
        ]);

        $response->assertSessionHasErrors(['general']);
        $this->assertEquals(0, JournalHeader::count());
    }

    public function test_cannot_post_negative_values(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('backoffice.opening-balances.store'), [
            'transaction_date' => '2026-09-01',
            'cash_in_drawer'   => -100000,
        ]);

        $response->assertSessionHasErrors(['cash_in_drawer']);
        $this->assertEquals(0, JournalHeader::count());
    }

    public function test_view_displays_existing_opening_balance_once_posted(): void
    {
        $this->actingAs($this->user);

        $service = app(OpeningBalanceService::class);
        $service->postOpeningBalance([
            'branch_id'        => $this->branch->id,
            'transaction_date' => '2026-09-01',
            'reference_number' => 'OB-EXISTING-01',
            'cash_in_drawer'   => 5000000,
            'bank_bca'         => 10000000,
        ], $this->user);

        $response = $this->get(route('backoffice.opening-balances.create'));
        $response->assertOk()
            ->assertSee('Saldo Awal Sistem Telah Dibukukan')
            ->assertSee('OB-EXISTING-01')
            ->assertSee('5.000.000')
            ->assertSee('10.000.000')
            ->assertSee('Total Keseimbangan:');
    }
}
