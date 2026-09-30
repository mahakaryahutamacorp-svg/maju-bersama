<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_backup_routes(): void
    {
        $this->post('/backoffice/backup/generate')->assertRedirect('/login');
        $this->get('/backoffice/backup/download')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_trigger_backup_generation(): void
    {
        $branch = Branch::create(['code' => 'CABANG-1', 'name' => 'Cabang Utama']);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
        ]);

        $response = $this->actingAs($user)
            ->from('/backoffice')
            ->post('/backoffice/backup/generate');

        $response->assertRedirect('/backoffice');
        $response->assertSessionHas('success');
    }

    public function test_authenticated_user_can_download_latest_backup(): void
    {
        $branch = Branch::create(['code' => 'CABANG-1', 'name' => 'Cabang Utama']);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
        ]);

        // First generate backup
        $this->actingAs($user)->post('/backoffice/backup/generate');

        // Then download
        $response = $this->actingAs($user)->get('/backoffice/backup/download');
        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.zip'));
    }

    public function test_dashboard_displays_backup_card(): void
    {
        $branch = Branch::create(['code' => 'CABANG-1', 'name' => 'Cabang Utama']);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
        ]);

        $response = $this->actingAs($user)->get('/backoffice');
        $response->assertOk();
        $response->assertSee('Sistem &amp; Keamanan', false);
        $response->assertSee('Buat Backup Baru');
        $response->assertSee('Unduh Backup Terakhir');
        $response->assertSee('/backoffice/backup/generate');
        $response->assertSee('/backoffice/backup/download');
    }
}
