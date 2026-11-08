<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Services\MasterCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SyncMasterCatalog extends Command
{
    public const REASON_NOT_IN_SOURCE = 'Tidak ada di acuan';

    protected $signature = 'catalog:sync-master
        {--source=* : Kode cabang acuan per grup, misalnya GUDANG-PUPUK atau PAKAN}
        {--match-name : Produk cabang yang namanya sama persis dengan produk acuan tetapi SKU-nya beda diganti ke SKU acuan, sehingga stok dan riwayatnya tetap}
        {--hold-cases : Arsipkan produk cabang yang tidak bisa disamakan otomatis agar diinput ulang manual}
        {--apply : Tulis perubahan. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Samakan nama, harga modal, harga jual, dan harga bertingkat produk ke semua cabang satu grup dari cabang acuan';

    private MasterCatalogService $catalog;

    /** @var Collection<int, Branch> */
    private Collection $branches;

    /** @var list<string> */
    private array $sourceCodes;

    /** @var list<array{branch: string, sku: string, name: string, stock: int, reason: string}> */
    private array $heldRows = [];

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
        if ($summary['pending'] === 0 && $summary['relinked'] === 0 && $summary['held'] === 0) {
            $this->info('Semua grup sudah sama dengan cabang acuannya.');

            return self::SUCCESS;
        }

        $message = "{$summary['pending']} produk acuan perlu disamakan, {$summary['relinked']} produk cabang dihubungkan lewat nama, {$summary['held']} produk cabang disembunyikan";
        if (! $apply) {
            $this->info("{$message}. Jalankan ulang dengan --apply untuk menulis perubahan.");

            return self::SUCCESS;
        }

        if (! $this->confirm("{$message}. Lanjutkan?", true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->heldRows = [];
        DB::transaction(fn () => $this->syncGroups(report: false, write: true));

        $this->info("Selesai: {$message}.");
        if ($this->heldRows !== []) {
            $this->info('Daftar produk yang disembunyikan: '.$this->writeHeldReport());
        }

        return self::SUCCESS;
    }

    /**
     * @return array{pending: int, relinked: int, held: int}
     */
    private function syncGroups(bool $report, bool $write): array
    {
        $totals = ['pending' => 0, 'relinked' => 0, 'held' => 0];

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
                $this->reportLine($report, "Grup {$root->code}: tidak ada produk di cabang acuan, dilewati.");

                continue;
            }

            $this->reportLine($report, "Grup {$root->code} - acuan {$source->code} ({$source->name}), {$products->count()} produk");

            $reasons = [];
            if ($this->option('match-name')) {
                $totals['relinked'] += $this->relinkByName($branchIds, $source, $products, $reasons, $report);
            }

            $cases = $this->unmatchedProducts($branchIds, $source, $products)
                ->each(fn (Product $product) => $product->setAttribute('hold_reason', $reasons[$product->id] ?? self::REASON_NOT_IN_SOURCE));

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
            $totals['pending'] += count($pending);

            if ($report) {
                $this->reportGroup($branchIds, $source, $products, $cases, $created, $updated);
            }

            if ($write) {
                foreach ($pending as $product) {
                    $this->catalog->syncToGroup($product);
                }
            }

            if ($this->option('hold-cases')) {
                $totals['held'] += $cases->count();
                if ($write) {
                    $cases->each(fn (Product $product) => $this->hold($product));
                }
            }
        }

        return $totals;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     * @param  array<int, string>  $reasons  filled with the reason a product could not be linked
     */
    private function relinkByName(array $branchIds, Branch $source, Collection $sourceProducts, array &$reasons, bool $report): int
    {
        $skusByName = $sourceProducts->groupBy(fn (Product $product) => $this->normalizeName($product->name))
            ->map(fn (Collection $group) => $group->pluck('sku')->unique()->values());

        $relinked = 0;
        foreach ($this->unmatchedProducts($branchIds, $source, $sourceProducts) as $product) {
            $skus = $skusByName->get($this->normalizeName($product->name));
            if ($skus === null) {
                continue;
            }

            if ($skus->count() > 1) {
                $reasons[$product->id] = "Nama ganda di acuan ({$skus->implode(', ')})";

                continue;
            }

            $targetSku = (string) $skus->first();
            $holder = Product::withoutGlobalScopes()
                ->where('branch_id', $product->branch_id)
                ->where('sku', $targetSku)
                ->first();

            if ($holder !== null) {
                $reasons[$product->id] = $holder->deleted_at === null
                    ? "Cabang sudah punya SKU {$targetSku}, perlu digabung manual"
                    : "SKU {$targetSku} di cabang ini sudah diarsipkan, perlu digabung manual";

                continue;
            }

            $label = $this->label($product);
            $product->forceFill(['sku' => $targetSku])->save();
            $relinked++;
            $this->reportLine($report, "  = Dihubungkan ke {$targetSku}: {$label}");
        }

        return $relinked;
    }

    /**
     * Active products in the other branches of the group whose SKU is not in the source branch.
     *
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     * @return Collection<int, Product>
     */
    private function unmatchedProducts(array $branchIds, Branch $source, Collection $sourceProducts): Collection
    {
        return Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->where('branch_id', '!=', $source->id)
            ->whereNotIn('sku', $sourceProducts->pluck('sku')->all())
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();
    }

    private function hold(Product $product): void
    {
        $reason = (string) $product->getAttribute('hold_reason');
        $this->heldRows[] = [
            'branch' => (string) $this->branches[$product->branch_id]->code,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'stock' => (int) $product->stock,
            'reason' => $reason,
        ];

        Product::withoutGlobalScopes()->whereKey($product->id)->update([
            'catalog_hold_reason' => $reason,
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeHeldReport(): string
    {
        $path = storage_path('app/catalog-sync/disembunyikan-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        fputcsv($handle, ['Cabang', 'SKU', 'Nama', 'Stok', 'Alasan']);
        foreach ($this->heldRows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);

        return $path;
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, Product>  $sourceProducts
     * @param  Collection<int, Product>  $cases
     * @param  array<int, int>  $created
     * @param  array<int, int>  $updated
     */
    private function reportGroup(array $branchIds, Branch $source, Collection $sourceProducts, Collection $cases, array $created, array $updated): void
    {
        $archived = Product::withoutGlobalScopes()
            ->whereNotNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->where('branch_id', '!=', $source->id)
            ->whereIn('sku', $sourceProducts->pluck('sku')->all())
            ->get(['id', 'branch_id']);

        $this->table(
            ['Cabang', 'Nama', 'Produk baru', 'Disamakan', 'Diarsipkan', 'Perlu input manual'],
            collect($branchIds)
                ->reject(fn (int $id) => $id === (int) $source->id)
                ->map(fn (int $id) => [
                    $this->branches[$id]->code,
                    $this->branches[$id]->name,
                    $created[$id],
                    $updated[$id],
                    $archived->where('branch_id', $id)->count(),
                    $cases->where('branch_id', $id)->count(),
                ])
                ->values()
                ->all(),
        );

        foreach ($cases as $product) {
            $this->line("  ! {$product->getAttribute('hold_reason')}: {$this->label($product)}");
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

    private function label(Product $product): string
    {
        return "[{$this->branches[$product->branch_id]->code}] {$product->sku} {$product->name} (stok {$product->stock})";
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
