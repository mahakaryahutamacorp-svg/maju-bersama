<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Tests\TestCase;

class PreviewDataTest extends TestCase
{
    public function test_preview_hides_soft_deleted_branches(): void
    {
        $office = Branch::create(['code' => 'OFFICE-PUPUK', 'name' => 'OFFICE MAJU BERSAMA']);
        Branch::create([
            'parent_id' => $office->id,
            'code' => 'CBG-ABI',
            'name' => 'TOKO MAJU BERSAMA ABI',
        ]);
        $retired = Branch::create(['code' => 'CBG-PAKAN', 'name' => 'TOKO PAKAN']);
        $retired->delete();

        $master = User::factory()->create([
            'branch_id' => $office->id,
            'role' => 'master',
        ]);

        $this->actingAs($master)
            ->get('/preview')
            ->assertOk()
            ->assertSee('TOKO MAJU BERSAMA ABI')
            ->assertSee('OFFICE MAJU BERSAMA')
            ->assertSee('2 cabang')
            ->assertDontSee('TOKO PAKAN')
            ->assertDontSee('CBG-PAKAN');
    }
}
