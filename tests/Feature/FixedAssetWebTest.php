<?php

namespace Tests\Feature;

use App\Models\AssetDepreciation;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private ChartOfAccount $assetAccount;
    private ChartOfAccount $depreciationAccount;
    private ChartOfAccount $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'    => 'Cabang Utama Bandung',
            'code'    => 'BDG-01',
            'address' => 'Jl. Asia Afrika No. 100, Bandung',
            'phone'   => '081234567890',
        ]);

        $this->admin = User::create([
            'name'      => 'Ahmad Admin',
            'email'     => 'ahmad@majubersama.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->assetAccount = ChartOfAccount::create([
            'code'      => '1310',
            'name'      => 'Kendaraan & Peralatan Kantor',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->depreciationAccount = ChartOfAccount::create([
            'code'      => '1311',
            'name'      => 'Akumulasi Penyusutan Kendaraan',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'code'      => '5130',
            'name'      => 'Beban Penyusutan Kendaraan',
            'type'      => 'expense',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/backoffice/fixed-assets')->assertRedirect('/login');
        $this->get('/backoffice/fixed-assets/create')->assertRedirect('/login');
        $this->post('/fixed-assets/run-depreciation', ['year_month' => '2026-09'])->assertRedirect('/login');
        $this->post('/backoffice/fixed-assets/run-depreciation', ['year_month' => '2026-09'])->assertRedirect('/login');
    }

    public function test_user_can_view_fixed_assets_index_and_create_pages(): void
    {
        $response = $this->actingAs($this->admin)->get('/backoffice/fixed-assets');
        $response->assertStatus(200);
        $response->assertSee('Harta Tetap &amp; Aktiva', false);
        $response->assertSee('Jalankan Penyusutan Bulan Ini');

        $createResponse = $this->actingAs($this->admin)->get('/backoffice/fixed-assets/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Pendaftaran Harta Tetap Baru');
        $createResponse->assertSee('Harga Perolehan');
        $createResponse->assertSee('Umur Ekonomis');
    }

    public function test_user_can_create_fixed_asset_with_straight_line_accessor(): void
    {
        $data = [
            'name'                    => 'Mobil Pick Up Grand Max 1.5',
            'purchase_date'           => '2026-01-15',
            'purchase_price'          => 120000000,
            'salvage_value'           => 24000000,
            'useful_life_months'      => 48,
            'asset_account_id'        => $this->assetAccount->id,
            'depreciation_account_id' => $this->depreciationAccount->id,
            'expense_account_id'      => $this->expenseAccount->id,
        ];

        $response = $this->actingAs($this->admin)->post('/backoffice/fixed-assets', $data);

        $response->assertRedirect('/backoffice/fixed-assets');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fixed_assets', [
            'branch_id'          => $this->branch->id,
            'name'               => 'Mobil Pick Up Grand Max 1.5',
            'useful_life_months' => 48,
            'status'             => 'ACTIVE',
        ]);

        $asset = FixedAsset::where('name', 'Mobil Pick Up Grand Max 1.5')->firstOrFail();
        // Rumus Garis Lurus: (120.000.000 - 24.000.000) / 48 = 96.000.000 / 48 = 2.000.000 / bulan
        $this->assertEquals(2000000.0, (float) $asset->monthly_depreciation);
    }

    public function test_validation_fails_for_invalid_input(): void
    {
        $response = $this->actingAs($this->admin)->post('/backoffice/fixed-assets', [
            'name'               => '',
            'purchase_price'     => -100,
            'salvage_value'      => 500, // lebih besar dari purchase_price
            'useful_life_months' => 0,
        ]);

        $response->assertSessionHasErrors([
            'name',
            'purchase_date',
            'purchase_price',
            'salvage_value',
            'useful_life_months',
            'asset_account_id',
            'depreciation_account_id',
            'expense_account_id',
        ]);
    }

    public function test_user_can_run_monthly_depreciation_and_creates_balanced_journal(): void
    {
        $asset = FixedAsset::create([
            'branch_id'               => $this->branch->id,
            'name'                    => 'Laptop Dell XPS Kasir Backoffice',
            'purchase_date'           => '2026-08-01',
            'purchase_price'          => 24000000,
            'salvage_value'           => 0,
            'useful_life_months'      => 24, // 1.000.000 / bulan
            'asset_account_id'        => $this->assetAccount->id,
            'depreciation_account_id' => $this->depreciationAccount->id,
            'expense_account_id'      => $this->expenseAccount->id,
            'status'                  => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->admin)->post('/fixed-assets/run-depreciation', [
            'year_month' => '2026-09',
        ]);

        $response->assertRedirect('/backoffice/fixed-assets');
        $response->assertSessionHas('success');

        // Pastikan record asset_depreciations terbentuk
        $this->assertDatabaseHas('asset_depreciations', [
            'fixed_asset_id'    => $asset->id,
            'depreciation_date' => '2026-09-30',
            'amount'            => 1000000.0000,
        ]);

        $depreciationLog = AssetDepreciation::where('fixed_asset_id', $asset->id)->firstOrFail();
        $this->assertNotNull($depreciationLog->journal_header_id);

        // Pastikan Jurnal Tercipta Seimbang (Double-Entry)
        $journal = JournalHeader::with('journalLines')->find($depreciationLog->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertEquals($this->branch->id, $journal->branch_id);

        $lines = $journal->journalLines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('chart_of_account_id', $this->expenseAccount->id);
        $creditLine = $lines->firstWhere('chart_of_account_id', $this->depreciationAccount->id);

        $this->assertNotNull($debitLine, 'Debit Beban Penyusutan harus ada.');
        $this->assertNotNull($creditLine, 'Kredit Akumulasi Penyusutan harus ada.');

        $this->assertEquals(1000000.0, (float) $debitLine->debit);
        $this->assertEquals(0.0, (float) $debitLine->credit);

        $this->assertEquals(0.0, (float) $creditLine->debit);
        $this->assertEquals(1000000.0, (float) $creditLine->credit);

        // Keseimbangan Jurnal Total Debit == Total Kredit
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(1000000.0, (float) $totalDebit);
    }

    public function test_monthly_depreciation_skips_duplicate_runs_in_same_period(): void
    {
        $asset = FixedAsset::create([
            'branch_id'               => $this->branch->id,
            'name'                    => 'Genset Silent 5KVA',
            'purchase_date'           => '2026-07-01',
            'purchase_price'          => 12000000,
            'salvage_value'           => 0,
            'useful_life_months'      => 12, // 1.000.000 / bulan
            'asset_account_id'        => $this->assetAccount->id,
            'depreciation_account_id' => $this->depreciationAccount->id,
            'expense_account_id'      => $this->expenseAccount->id,
            'status'                  => 'ACTIVE',
        ]);

        $service = app(FixedAssetService::class);

        // Run pertama
        $result1 = $service->runMonthlyDepreciation('2026-09', $this->branch->id);
        $this->assertEquals(1, $result1['processed_count']);
        $this->assertEquals(0, $result1['skipped_count']);
        $this->assertEquals(1000000.0, $result1['total_amount']);

        // Run kedua pada bulan yang sama
        $result2 = $service->runMonthlyDepreciation('2026-09', $this->branch->id);
        $this->assertEquals(0, $result2['processed_count']);
        $this->assertEquals(1, $result2['skipped_count']);
        $this->assertEquals(0.0, $result2['total_amount']);

        // Pastikan hanya 1 record log dan 1 jurnal yang tercipta
        $this->assertEquals(1, AssetDepreciation::where('fixed_asset_id', $asset->id)->count());
    }

    public function test_monthly_depreciation_caps_at_salvage_value_and_disposes_asset(): void
    {
        // Nilai perolehan 1.000.000, nilai sisa 200.000 -> Tersusutkan = 800.000
        // Umur 2 bulan -> 400.000 / bulan
        $asset = FixedAsset::create([
            'branch_id'               => $this->branch->id,
            'name'                    => 'Printer Barcode Zebra',
            'purchase_date'           => '2026-01-01',
            'purchase_price'          => 1000000,
            'salvage_value'           => 200000,
            'useful_life_months'      => 2,
            'asset_account_id'        => $this->assetAccount->id,
            'depreciation_account_id' => $this->depreciationAccount->id,
            'expense_account_id'      => $this->expenseAccount->id,
            'status'                  => 'ACTIVE',
        ]);

        $service = app(FixedAssetService::class);

        // Bulan ke-1 (2026-01)
        $res1 = $service->runMonthlyDepreciation('2026-01', $this->branch->id);
        $this->assertEquals(1, $res1['processed_count']);
        $this->assertEquals(400000.0, $res1['total_amount']);

        $asset->refresh();
        $this->assertEquals('ACTIVE', $asset->status);

        // Bulan ke-2 (2026-02) -> mencukupi 800.000
        $res2 = $service->runMonthlyDepreciation('2026-02', $this->branch->id);
        $this->assertEquals(1, $res2['processed_count']);
        $this->assertEquals(400000.0, $res2['total_amount']);

        $asset->refresh();
        // Aset berubah status menjadi DISPOSED karena nilai bukunya telah mencapai nilai residu
        $this->assertEquals('DISPOSED', $asset->status);

        // Bulan ke-3 (2026-03) -> harus di-skip karena status DISPOSED / limit tercapai
        $res3 = $service->runMonthlyDepreciation('2026-03', $this->branch->id);
        $this->assertEquals(0, $res3['processed_count']);
        $this->assertEquals(0.0, $res3['total_amount']);
    }

    public function test_fixed_asset_report_center_reflects_actual_assets_and_accumulated_depreciation(): void
    {
        $asset = FixedAsset::create([
            'branch_id'               => $this->branch->id,
            'name'                    => 'Timbangan Digital Presisi',
            'purchase_date'           => '2026-01-01',
            'purchase_price'          => 5000000,
            'salvage_value'           => 500000,
            'useful_life_months'      => 10,
            'asset_account_id'        => $this->assetAccount->id,
            'depreciation_account_id' => $this->depreciationAccount->id,
            'expense_account_id'      => $this->expenseAccount->id,
            'status'                  => 'ACTIVE',
        ]);

        // Jalankan depresiasi
        app(FixedAssetService::class)->runMonthlyDepreciation('2026-01', $this->branch->id);

        $response = $this->actingAs($this->admin)->get(route('reports.fixed-assets'));
        $response->assertStatus(200);
        $response->assertSee('Timbangan Digital Presisi');
        $response->assertSee('Rp 5.000.000,00'); // Nilai perolehan
        $response->assertSee('Rp 450.000,00');   // Akumulasi depresiasi ((5.000.000 - 500.000) / 10 = 450.000)
        $response->assertSee('Rp 4.550.000,00'); // Nilai buku (5.000.000 - 450.000 = 4.550.000)
    }
}
