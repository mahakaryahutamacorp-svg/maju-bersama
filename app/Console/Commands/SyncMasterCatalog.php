<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Services\MasterCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncMasterCatalog extends Command
{
    protected $signature = 'catalog:sync-master
        {--source=* : Kode cabang acuan per grup, misalnya GUDANG-PUPUK atau PAKAN}
        {--apply : Tulis perubahan. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Samakan nama, harga modal, harga jual, dan harga bertingkat produk ke semua cabang satu grup dari cabang acuan';

    public function handle(MasterCatalogService $catalog): int
    {
        $apply = (bool) $this->option('apply');
        $sourceCodes = array_map('strtoupper', (array) $this->option('source'));
        $branches = Branch::query()->get(['id', 'code', 'name', 'parent_id'])->keyBy('id');

        $this->info($apply ? 'Mode: TULIS PERUBAHAN' : 'Mode: SIMULASI (tambahkan --apply untuk menulis perubahan)');

        $pending = [];
        foreach ($catalog->groups() as $rootId => $branchIds) {
            if (count($branchIds) < 2) {
                continue;
            }

            $root = $branches[$rootId];
            $source = $this->sourceBranch($branchIds, $branches, $sourceCodes);
            $products = $source
                ? Product::withoutGlobalScopes()->whereNull('deleted_at')->where('branch_id', $source->id)->orderBy('id')->get()
                : collect();

            $this->newLine();
            if ($products->isEmpty()) {
                $this->line("Grup {$root->code}: tidak ada produk di cabang acuan, dilewati.");

                continue;
            }

            $this->line("Grup {$root->code} - acuan {$source->code} ({$source->name}), {$products->count()} produk");

            $created = array_fill_keys($branchIds, 0);
            $updated = array_fill_keys($branchIds, 0);
            foreach ($products as $product) {
                $plan = $catalog->plan($product);
                foreach ($plan['create'] as $branchId) {
                    $created[$branchId]++;
                }
                foreach ($plan['update'] as $branchId) {
                    $updated[$branchId]++;
                }
                if ($plan['create'] !== [] || $plan['update'] !== []) {
                    $pending[] = $product;
                }
            }

            $extras = $this->productsMissingFromSource($branchIds, $source, $products);

            $this->table(
                ['Cabang', 'Nama', 'Produk baru', 'Disamakan', 'Tidak ada di acuan'],
                collect($branchIds)
                    ->reject(fn (int $id) => $id === (int) $source->id)
                    ->map(fn (int $id) => [
                        $branches[$id]->code,
                        $branches[$id]->name,
                        $created[$id],
                        $updated[$id],
                        $extras->where('branch_id', $id)->count(),
                    ])
                    ->values()
                    ->all(),
            );

            foreach ($extras->take(10) as $extra) {
                $this->line("  - Tidak ada di acuan: [{$branches[$extra->branch_id]->code}] {$extra->sku} {$extra->name}");
            }
        }

        $this->newLine();
        if ($pending === []) {
            $this->info('Semua grup sudah sama dengan cabang acuannya.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->info(count($pending).' produk acuan perlu disamakan. Jalankan ulang dengan --apply untuk menulis perubahan.');

            return self::SUCCESS;
        }

        if (! $this->confirm(count($pending).' produk acuan akan disamakan ke cabang lain dalam grupnya. Lanjutkan?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        foreach ($pending as $product) {
            $catalog->syncToGroup($product);
        }

        $this->info(count($pending).' produk acuan berhasil disamakan.');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Branch>  $branches
     * @param  list<string>  $sourceCodes
     */
    private function sourceBranch(array $branchIds, Collection $branches, array $sourceCodes): ?Branch
    {
        foreach ($branchIds as $id) {
            if (in_array(strtoupper((string) $branches[$id]->code), $sourceCodes, true)) {
                return $branches[$id];
            }
        }

        $busiest = Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->orderByDesc('total')
            ->orderBy('branch_id')
            ->first();

        return $busiest ? $branches[(int) $busiest->branch_id] : null;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     * @return Collection<int, Product>
     */
    private function productsMissingFromSource(array $branchIds, Branch $source, Collection $sourceProducts): Collection
    {
        return Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->where('branch_id', '!=', $source->id)
            ->whereNotIn('sku', $sourceProducts->pluck('sku')->all())
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'sku', 'name']);
    }
}
