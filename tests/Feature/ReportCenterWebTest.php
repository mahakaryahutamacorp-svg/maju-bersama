<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'code' => 'PUSAT',
            'name' => 'Kantor Pusat',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'master',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backoffice/reports');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_report_center(): void
    {
        $response = $this->actingAs($this->user)->get('/backoffice/reports');

        $response->assertStatus(200);
        $response->assertSee('Pusat Laporan');
        $response->assertSee('x-data="{ activeTab: \'keuangan\'', false);
    }

    public function test_report_center_has_all_five_categories(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Keuangan');
        $response->assertSee('Penjualan &amp; Piutang', false);
        $response->assertSee('Pembelian &amp; Hutang', false);
        $response->assertSee('Persediaan / Barang');
        $response->assertSee('Harta Tetap');
    }

    public function test_report_center_contains_required_financial_and_inventory_reports(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);

        // Keuangan
        $response->assertSee('Laba Rugi Standar');
        $response->assertSee('Neraca Saldo');
        $response->assertSee('Neraca Standar');
        $response->assertSee('Arus Kas');

        // Persediaan
        $response->assertSee('Kartu Stok / Mutasi Barang');
        $response->assertSee('Daftar Barang per Gudang');
    }

    public function test_backoffice_sidebar_has_report_center_menu(): void
    {
        $response = $this->actingAs($this->user)->get('/backoffice');

        $response->assertStatus(200);
        $response->assertSee('Pusat Laporan');
        $response->assertSee(route('reports.index'), false);
    }
}
