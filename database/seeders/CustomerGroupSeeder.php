<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Umum/Retail',
                'notes' => 'Pelanggan retail umum dengan harga standar.',
            ],
            [
                'name' => 'Petani',
                'notes' => 'Pelanggan komunitas petani dengan harga subsidi / kemitraan.',
            ],
            [
                'name' => 'Grosir',
                'notes' => 'Pelanggan partai besar / toko mitra dengan harga grosir.',
            ],
        ];

        foreach ($groups as $group) {
            CustomerGroup::updateOrCreate(['name' => $group['name']], $group);
        }
    }
}
