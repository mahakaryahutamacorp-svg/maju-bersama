<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\BusinessStructureSeeder;
use Tests\TestCase;

class BusinessStructureSeederTest extends TestCase
{
    public function test_seeder_removes_empty_dummy_branches_and_builds_both_divisions(): void
    {
        Branch::create(['code' => 'MAJUBERSAMA-5', 'name' => 'majubersama 5']);
        $duplicate = Branch::create(['code' => 'CBG-PAKAN', 'name' => 'TOKO PAKAN']);
        $kept = Branch::create(['code' => 'CBG-PKN', 'name' => 'TOKO PAKAN lama']);
        User::factory()->create([
            'branch_id' => $kept->id,
            'email' => 'asep@mb.pkn',
            'role' => 'cashier',
            'password' => 'password',
        ]);

        $this->seed(BusinessStructureSeeder::class);

        $this->assertDatabaseMissing('branches', ['code' => 'MAJUBERSAMA-5']);
        $this->assertDatabaseMissing('branches', ['code' => 'CBG-PAKAN']);
        $this->assertNull($duplicate->fresh());

        $office = Branch::where('code', 'OFFICE-PUPUK')->firstOrFail();
        $this->assertSame('MAJU BERSAMA', $office->name);
        $this->assertNull($office->parent_id);
        $this->assertSame(0, $office->users()->count());

        $abi = Branch::where('code', 'CBG-ABI')->firstOrFail();
        $this->assertSame($office->id, $abi->parent_id);
        $this->assertSame('TOKO MAJU BERSAMA ABI', $abi->name);
        $this->assertDatabaseHas('users', ['email' => 'admin@mb.abi', 'branch_id' => $abi->id, 'role' => 'branch_admin']);
        $this->assertDatabaseHas('users', ['email' => 'pipin@kasir.pupuk', 'branch_id' => $abi->id, 'role' => 'cashier']);

        $pakanOffice = Branch::where('code', 'OFFICE-PAKAN')->firstOrFail();
        $pakanStore = Branch::where('code', 'CBG-PKN')->firstOrFail();
        $this->assertSame('TOKO PAKAN', $pakanStore->name);
        $this->assertSame($pakanOffice->id, $pakanStore->parent_id);
        $this->assertSame($kept->id, $pakanStore->id);
        $this->assertDatabaseHas('users', ['email' => 'asep@mb.pkn', 'branch_id' => $pakanStore->id, 'role' => 'branch_admin']);
        $this->assertDatabaseHas('users', ['email' => 'kasir@pakan.mb', 'role' => 'cashier']);
        $this->assertDatabaseHas('users', ['email' => 'gudang@pupuk.178', 'role' => 'branch_admin']);
        $this->assertDatabaseHas('users', ['email' => 'gudang@pakan.178', 'role' => 'branch_admin']);
        $this->assertSame(0, $pakanOffice->users()->count());
    }

    public function test_seeder_keeps_a_dummy_branch_that_already_has_a_sale(): void
    {
        $branch = Branch::create(['code' => 'MAJUBERSAMA-5', 'name' => 'majubersama 5']);
        Sale::create([
            'branch_id' => $branch->id,
            'receipt_number' => 'INV-DUMMY-5',
            'total_amount' => 10000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $this->seed(BusinessStructureSeeder::class);

        $this->assertNotNull($branch->fresh());
        $this->assertDatabaseHas('sales', ['receipt_number' => 'INV-DUMMY-5', 'branch_id' => $branch->id]);
    }

    public function test_seeder_can_run_twice_without_duplicating_branches_or_resetting_passwords(): void
    {
        $this->seed(BusinessStructureSeeder::class);

        $user = User::where('email', 'eva@admin.178')->firstOrFail();
        $user->password = 'rahasia-sendiri';
        $user->save();

        $this->seed(BusinessStructureSeeder::class);

        $this->assertSame(1, Branch::where('code', 'CBG-MAMA')->count());
        $this->assertSame(1, User::where('email', 'eva@admin.178')->count());
        $this->assertTrue(password_verify('rahasia-sendiri', $user->fresh()->password));
    }
}
