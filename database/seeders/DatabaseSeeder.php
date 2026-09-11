<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ChartOfAccountSeeder::class);

        $branchId = DB::table('branches')->insertGetId([
            'code' => 'PUSAT',
            'name' => 'Pusat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'branch_id' => $branchId,
            'name' => 'Admin Pusat',
            'email' => 'admin@pusat.test',
            'role' => 'admin',
        ]);

        $this->call(ProductSeeder::class);
    }
}
