<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class BackofficeMonitoringTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-11-11 15:30:00', 'Asia/Jakarta');
        $this->travelTo($this->now);
    }

    public function test_owner_sees_today_sales_for_every_branch_including_zero(): void
    {
        [$melati, $mawar, $senyap, $master] = $this->branches();
        $this->sales($melati, $mawar, $master);

        JournalHeader::create([
            'branch_id' => $melati->id,
            'user_id' => $master->id,
            'transaction_date' => '2026-11-11',
            'reference_number' => 'POS-1001',
            'description' => 'POS Sale',
        ]);
        JournalHeader::create([
            'branch_id' => $melati->id,
            'user_id' => $master->id,
            'transaction_date' => '2026-11-11',
            'reference_number' => 'JV-2907',
            'description' => 'Saldo Awal Piutang - DHANI (Nota: 2907)',
        ]);

        $this->actingAs($master)
            ->get('/backoffice')
            ->assertOk()
            ->assertSeeInOrder([
                'Monitor penjualan',
                'Rp 1.750.000',
                'Total hari ini',
                'Hari ini',
                'Minggu ini',
                'Bulan ini',
                'Tahun ini',
                'Toko Melati',
                'Rp 1.500.000',
                'Toko Mawar',
                'Rp 250.000',
                'Gudang Senyap',
                'Rp 0',
                'POS-1001',
                'Penjualan kasir',
            ])
            ->assertDontSee('POS Sale')
            ->assertDontSee('JV-2907')
            ->assertDontSee('Saldo Awal Piutang');
    }

    public function test_period_selector_returns_week_month_and_year_totals(): void
    {
        [$melati, $mawar, $senyap, $master] = $this->branches();
        $this->sales($melati, $mawar, $master);

        $this->actingAs($master)
            ->getJson('/backoffice/monitoring?period=week')
            ->assertOk()
            ->assertJsonPath('period', 'week')
            ->assertJsonPath('total_label', 'Total minggu ini')
            ->assertJsonPath('total_formatted', 'Rp 1.850.000')
            ->assertJsonPath('locations.0.name', 'Toko Melati')
            ->assertJsonPath('locations.0.total_formatted', 'Rp 1.600.000')
            ->assertJsonPath('locations.0.receipt_count', 2);

        $this->actingAs($master)
            ->getJson('/backoffice/monitoring?period=month')
            ->assertOk()
            ->assertJsonPath('total_formatted', 'Rp 2.849.000')
            ->assertJsonPath('total_label', 'Total bulan ini');

        $this->actingAs($master)
            ->getJson('/backoffice/monitoring?period=year')
            ->assertOk()
            ->assertJsonPath('total_formatted', 'Rp 2.889.000')
            ->assertJsonPath('total_label', 'Total tahun ini');

        $this->actingAs($master)
            ->getJson('/backoffice/monitoring?period=lainnya')
            ->assertOk()
            ->assertJsonPath('period', 'day')
            ->assertJsonPath('total_formatted', 'Rp 1.750.000');
    }

    public function test_guest_cannot_open_monitoring(): void
    {
        $this->get('/backoffice/monitoring')->assertRedirect('/login');
    }

    public function test_branch_admin_only_sees_their_location(): void
    {
        [$melati, $mawar, $senyap] = $this->branches();
        $admin = User::factory()->create([
            'branch_id' => $mawar->id,
            'role' => 'branch_admin',
        ]);
        $this->sales($melati, $mawar, $admin);

        $this->actingAs($admin)
            ->get('/backoffice')
            ->assertOk()
            ->assertSee('Toko Mawar')
            ->assertSee('Rp 250.000')
            ->assertDontSee('Toko Melati')
            ->assertDontSee('Gudang Senyap');

        $this->actingAs($admin)
            ->getJson('/backoffice/monitoring?period=year')
            ->assertOk()
            ->assertJsonCount(1, 'locations')
            ->assertJsonPath('locations.0.name', 'Toko Mawar')
            ->assertJsonPath('total_formatted', 'Rp 250.000')
            ->assertJsonMissing(['name' => 'Toko Melati']);
    }

    /**
     * @return array{0: Branch, 1: Branch, 2: Branch, 3: User}
     */
    private function branches(): array
    {
        $melati = Branch::create(['code' => 'MELATI', 'name' => 'Toko Melati']);
        $mawar = Branch::create(['code' => 'MAWAR', 'name' => 'Toko Mawar']);
        $senyap = Branch::create(['code' => 'SENYAP', 'name' => 'Gudang Senyap']);
        $master = User::factory()->create([
            'branch_id' => $melati->id,
            'role' => 'master',
        ]);

        return [$melati, $mawar, $senyap, $master];
    }

    private function sales(Branch $melati, Branch $mawar, User $user): void
    {
        $today = $this->sale($melati, $user, 'MLT-HARI', 1_500_000, $this->now->copy()->setTime(15, 0));
        $this->sale($mawar, $user, 'MWR-HARI', 250_000, $this->now->copy()->setTime(14, 0));
        $this->sale($melati, $user, 'MLT-SENIN', 100_000, Carbon::parse('2026-11-09 09:00:00', 'Asia/Jakarta'));
        $this->sale($melati, $user, 'MLT-MINGGU', 999_000, Carbon::parse('2026-11-08 23:00:00', 'Asia/Jakarta'));
        $this->sale($melati, $user, 'MLT-OKT', 40_000, Carbon::parse('2026-10-15 11:00:00', 'Asia/Jakarta'));
        $this->sale($melati, $user, 'MLT-LALU', 77_000, Carbon::parse('2025-12-31 11:00:00', 'Asia/Jakarta'));
        $this->sale($melati, $user, 'MLT-NANTI', 333_000, $this->now->copy()->setTime(18, 0));
        $this->sale($melati, $user, 'MLT-BATAL', 888_000, $this->now->copy()->setTime(12, 0), 'void');

        $account = ChartOfAccount::updateOrCreate(
            ['code' => '1110'],
            ['name' => 'Kas', 'type' => 'asset']
        );

        SalesReturn::create([
            'branch_id' => $melati->id,
            'sale_id' => $today->id,
            'user_id' => $user->id,
            'return_date' => '2026-11-11',
            'reference_number' => 'RT-1001',
            'chart_of_account_id' => $account->id,
            'total_amount' => 500_000,
            'status' => 'completed',
        ]);
    }

    private function sale(Branch $branch, User $user, string $number, int $amount, Carbon $at, string $status = 'completed'): Sale
    {
        $sale = Sale::create([
            'branch_id' => $branch->id,
            'receipt_number' => $number,
            'total_amount' => $amount,
            'payment_method' => 'cash',
            'status' => $status,
            'created_by' => $user->id,
        ]);
        $sale->created_at = $at;
        $sale->updated_at = $at;
        $sale->save();

        return $sale;
    }
}
