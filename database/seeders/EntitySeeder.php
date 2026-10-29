<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntitySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const BRANCH_ENTITY = [
        'OFFICE-PUPUK' => Entity::CODE_MB_PUPUK,
        'GUDANG-PUPUK' => Entity::CODE_MB_PUPUK,
        'CBG-ABI' => Entity::CODE_MB_PUPUK,
        'CBG-MAMA' => Entity::CODE_MB_PUPUK,
        'CBG-AROFAH' => Entity::CODE_MB_PUPUK,
        'CBG-TULUSAYU' => Entity::CODE_MB_PUPUK,
        'CBG-LUBUKHARJO' => Entity::CODE_MB_PUPUK,
        'PAKAN' => Entity::CODE_DEKA,
    ];

    public function run(): void
    {
        $entities = collect([
            Entity::CODE_MB_PUPUK => 'Maju Bersama – Jaringan Ritel Pupuk',
            Entity::CODE_DEKA => 'CV. Deka Agro Mandiri',
        ])->map(fn (string $legalName, string $code) => Entity::updateOrCreate(
            ['code' => $code],
            ['legal_name' => $legalName],
        ));

        foreach (Branch::withTrashed()->orderBy('id')->get(['id', 'code', 'entity_id']) as $branch) {
            $entityCode = self::BRANCH_ENTITY[$branch->code] ?? null;

            if ($entityCode === null) {
                $this->command?->warn("Cabang {$branch->code} (id {$branch->id}) tidak dikenal; entity_id tidak diisi.");

                continue;
            }

            $entityId = $entities[$entityCode]->id;

            if ((int) $branch->entity_id !== $entityId) {
                DB::table('branches')->where('id', $branch->id)->update(['entity_id' => $entityId]);
            }
        }
    }
}
