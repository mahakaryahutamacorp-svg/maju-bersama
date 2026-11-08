<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Services\MasterCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncMasterCatalog extends Command
{
    protected $signature = 'catalog:sync-master
        {--source=* : Kode cabang acuan per grup, misalnya GUDANG-PUPUK atau PAKAN}
        {--match-name : Produk cabang yang namanya sama persis dengan produk acuan tetapi SKU-nya beda diganti ke SKU acuan, sehingga stok dan riwayatnya tetap}
        {--apply : Tulis perubahan. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Samakan nama, harga modal, harga jual, dan harga bertingkat produk ke semua cabang satu grup dari cabang acuan';

    private MasterCatalogService $catalog;

    /** @var Collection<int, Branch> */
    private Collection $branches;

    /** @var list<string> */
    private array $sourceCodes;

    public function handle(MasterCatalogService $catalog): int
    {
        $this->catalog = $catalog;
        $this->sourceCodes = array_map('strtoupper', (array) $this->option('source'));
        $this->branches = Branch::query()->get(['id', 'code', 'name', 'parent_id'])->keyBy('id');
        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'Mode: TULIS PERUBAHAN' : 'Mode: SIMULASI (tambahkan --apply untuk menulis perubahan)');

        // Simulasi menjalankan semua langkah lalu membatalkannya, sehingga angkanya sama dengan hasil --apply.
        DB::beginTransaction();
        try {
            $summary = $this->syncGroups(report: true, write: false);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        if ($summary['pending'] === 0 && $summary['relinked'] === 0) {
            $this->info('Semua grup sudah sama dengan cabang acuannya.');

            return self::SUCCESS;
        }

        $message = "{$summary['pending']} produk acuan perlu disamakan, {$summary['relinked']} produk cabang dihubungkan lewat nama";
        if (! $apply) {
            $this->info("{$message}. Jalankan ulang dengan --apply untuk menulis perubahan.");

            return self::SUCCESS;
        }

        if (! $this->confirm("{$message}. Lanjutkan?", true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->syncGroups(report: false, write: true));

        $this->info("Selesai: {$message}.");

        return self::SUCCESS;
    }

    /**
     * @return array{pending: int, relinked: int}
     */
    private function syncGroups(bool $report, bool $write): array
    {
        $pendingTotal = 0;
        $relinkedTotal = 0;

        foreach ($this->catalog->groups() as $rootId => $branchIds) {
            if (count($branchIds) < 2) {
                continue;
            }

            $root = $this->branches[$rootId];
            $source = $this->sourceBranch($branchIds);
            $products = $source
                ? Product::withoutGlobalScopes()->whereNull('deleted_at')->where('branch_id', $source->id)->orderBy('id')->get()
                : collect();

            if ($report) {
                $this->newLine();
            }
            if ($products->isEmpty()) {
                if ($report) {
                    $this->line("Grup {$root->code}: tidak ada produk di cabang acuan, dilewati.");
                }

                continue;
            }

            if ($report) {
                $this->line("Grup {$root->code} - acuan {$source->code} ({$source->name}), {$products->count()} produk");
            }

            if ($this->option('match-name')) {
                $relinkedTotal += $this->relinkByName($branchIds, $source, $products, $report);
            }

            $created = array_fill_keys($branchIds, 0);
            $updated = array_fill_keys($branchIds, 0);
            $pending = [];
            foreach ($products as $product) {
                $plan = $this->catalog->plan($product);
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
            $pendingTotal += count($pending);

            if ($report) {
                $this->reportGroup($branchIds, $source, $products, $created, $updated);
            }

            if ($write) {
                foreach ($pending as $product) {
                    $this->catalog->syncToGroup($product);
                }
            }
        }

        return ['pending' => $pendingTotal, 'relinked' => $relinkedTotal];
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     */
    private function relinkByName(array $branchIds, Branch $source, Collection $sourceProducts, bool $report): int
    {
        $skusByName = $sourceProducts->groupBy(fn (Product $product) => $this->normalizeName($product->name))
            ->map(fn (Collection $group) => $group->pluck('sku')->unique()->values());

        $candidates = Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->where('branch_id', '!=', $source->id)
            ->whereNotIn('sku', $sourceProducts->pluck('sku')->all())
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get();

        $relinked = 0;
        foreach ($candidates as $product) {
            $skus = $skusByName->get($this->normalizeName($product->name));
            if ($skus === null) {
                continue;
            }

            $label = "[{$this->branches[$product->branch_id]->code}] {$product->sku} {$product->name} (stok {$product->stock})";

            if ($skus->count() > 1) {
                $this->reportLine($report, "  ! Nama ganda di acuan ({$skus->implode(', ')}), tidak dihubungkan: {$label}");

                continue;
            }

            $targetSku = (string) $skus->first();
            $taken = Product::withoutGlobalScopes()
                ->where('branch_id', $product->branch_id)
                ->where('sku', $targetSku)
                ->exists();

            if ($taken) {
                $this->reportLine($report, "  ! Cabang sudah punya SKU {$targetSku}, perlu digabung manual: {$label}");

                continue;
            }

            $product->forceFill(['sku' => $targetSku])->save();
            $relinked++;
            $this->reportLine($report, "  = Dihubungkan ke {$targetSku}: {$label}");
        }

        return $relinked;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     * @param  array<int, int>  $created
     * @param  array<int, int>  $updated
     */
    private function reportGroup(array $branchIds, Branch $source, Collection $sourceProducts, array $created, array $updated): void
    {
        $others = Product::withoutGlobalScopes()
            ->whereIn('branch_id', $branchIds)
            ->where('branch_id', '!=', $source->id)
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'sku', 'name', 'stock', 'deleted_at']);

        $sourceSkus = $sourceProducts->pluck('sku')->flip();
        $extras = $others->filter(fn (Product $product) => $product->deleted_at === null && ! $sourceSkus->has($product->sku));
        $archived = $others->filter(fn (Product $product) => $product->deleted_at !== null && $sourceSkus->has($product->sku));

        $this->table(
            ['Cabang', 'Nama', 'Produk baru', 'Disamakan', 'Diarsipkan', 'Tidak ada di acuan'],
            collect($branchIds)
                ->reject(fn (int $id) => $id === (int) $source->id)
                ->map(fn (int $id) => [
                    $this->branches[$id]->code,
                    $this->branches[$id]->name,
                    $created[$id],
                    $updated[$id],
                    $archived->where('branch_id', $id)->count(),
                    $extras->where('branch_id', $id)->count(),
                ])
                ->values()
                ->all(),
        );

        foreach ($extras as $extra) {
            $this->line("  - Tidak ada di acuan: [{$this->branches[$extra->branch_id]->code}] {$extra->sku} {$extra->name} (stok {$extra->stock})");
        }
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function sourceBranch(array $branchIds): ?Branch
    {
        foreach ($branchIds as $id) {
            if (in_array(strtoupper((string) $this->branches[$id]->code), $this->sourceCodes, true)) {
                return $this->branches[$id];
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

        return $busiest ? $this->branches[(int) $busiest->branch_id] : null;
    }

    private function normalizeName(?string $name): string
    {
        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $name)));
    }

    private function reportLine(bool $report, string $line): void
    {
        if ($report) {
            $this->line($line);
        }
    }
}
