<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_json_is_accessible(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);

        $json = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($json);
        $this->assertEquals('Maju Bersama POS', $json['name']);
        $this->assertEquals('/pos', $json['start_url']);
        $this->assertEquals('standalone', $json['display']);
    }

    public function test_service_worker_script_exists(): void
    {
        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath);

        $content = file_get_contents($swPath);
        $this->assertStringContainsString('pos-cache-v1', $content);
        $this->assertStringContainsString('/pos', $content);
        $this->assertStringContainsString('dexie', $content);
        $this->assertStringContainsString('event.request.method !== \'GET\'', $content);
    }

    public function test_pos_view_contains_pwa_and_dexie_injections(): void
    {
        $branch = Branch::create(['code' => 'PWA-1', 'name' => 'Cabang Test PWA']);
        $cashier = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'cashier',
        ]);

        $response = $this->actingAs($cashier)->get('/pos');
        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
        $response->assertSee('dexie@3.2.4/dist/dexie.min.js', false);
        $response->assertSee('window.posDB = new Dexie(\'MajuBersamaPOS\');', false);
        $response->assertSee('sync_queue', false);
        $response->assertSee('navigator.serviceWorker.register(\'/sw.js\')', false);
    }

    public function test_pos_view_implements_offline_checkout_and_sync_methods(): void
    {
        $branch = Branch::create(['code' => 'PWA-2', 'name' => 'Cabang Test PWA 2']);
        $cashier = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'cashier',
        ]);

        $response = $this->actingAs($cashier)->get('/pos');
        $response->assertOk();
        $response->assertSee('!navigator.onLine', false);
        $response->assertSee('window.posDB.sync_queue.add', false);
        $response->assertSee('OFFLINE-', false);
        $response->assertSee('syncOfflineSales()', false);
        $response->assertSee('window.posDB.sync_queue.toArray()', false);
        $response->assertSee('window.posDB.sync_queue.delete', false);
        $response->assertSee('addEventListener(\'online\'', false);
    }
}
