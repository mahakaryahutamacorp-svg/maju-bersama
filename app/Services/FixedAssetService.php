<?php

namespace App\Services;

use App\Models\AssetDepreciation;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixedAssetService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * Jalankan proses penyusutan bulanan (Straight-Line Depreciation) untuk periode tertentu.
     *
     * @param string $yearMonth Format 'YYYY-MM' (contoh: '2026-09')
     * @param int|null $branchId Optional filter cabang
     * @return array
     */
    public function runMonthlyDepreciation(string $yearMonth, ?int $branchId = null): array
    {
        return DB::transaction(function () use ($yearMonth, $branchId) {
            $parsedDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
            $startDate = $parsedDate->copy()->startOfMonth()->toDateString();
            $endDate = $parsedDate->copy()->endOfMonth()->toDateString();
            $depreciationDate = $endDate;

            $query = FixedAsset::withoutGlobalScopes()
                ->where('status', 'ACTIVE')
                ->whereDate('purchase_date', '<=', $endDate);

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            $assets = $query->get();

            $processedCount = 0;
            $skippedCount = 0;
            $totalDepreciated = 0.0;
            $details = [];

            foreach ($assets as $asset) {
                // 1. Cek apakah penyusutan untuk bulan tersebut sudah pernah di-run (hindari duplikasi)
                $alreadyRun = AssetDepreciation::where('fixed_asset_id', $asset->id)
                    ->where(function ($q) use ($startDate, $endDate, $parsedDate) {
                        $q->whereBetween('depreciation_date', [$startDate, $endDate])
                          ->orWhereBetween('depreciation_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                          ->orWhere(function ($sub) use ($parsedDate) {
                              $sub->whereYear('depreciation_date', $parsedDate->year)
                                  ->whereMonth('depreciation_date', $parsedDate->month);
                          });
                    })
                    ->exists();

                if ($alreadyRun) {
                    $skippedCount++;
                    continue;
                }

                // 2. Cek apakah akumulasi penyusutan sudah mencapai (purchase_price - salvage_value)
                $accumulated = (float) AssetDepreciation::where('fixed_asset_id', $asset->id)->sum('amount');
                $depreciableLimit = (float) $asset->purchase_price - (float) $asset->salvage_value;
                $remainingDepreciable = $depreciableLimit - $accumulated;

                if ($remainingDepreciable <= 0.0001) {
                    $asset->update(['status' => 'DISPOSED']);
                    $skippedCount++;
                    continue;
                }

                // 3. Hitung nilai depresiasi bulan ini (Straight-Line)
                $monthly = (float) $asset->monthly_depreciation;
                $amountToDepreciate = min($monthly, $remainingDepreciable);
                $amountToDepreciate = round($amountToDepreciate, 2);

                if ($amountToDepreciate <= 0) {
                    $skippedCount++;
                    continue;
                }

                // 4. Jurnal Otomatis via JournalEntryService
                // Debit: Beban Penyusutan (expense_account_id)
                // Kredit: Akumulasi Penyusutan (depreciation_account_id)
                $cleanYM = str_replace('-', '', $yearMonth);
                $refNumber = "DEP-{$asset->branch_id}-{$cleanYM}-{$asset->id}";
                $description = "Penyusutan Aset Tetap: {$asset->name} ({$yearMonth})";

                $journal = $this->journalEntryService->createEntry([
                    'branch_id' => $asset->branch_id,
                    'user_id' => auth()->id(),
                    'transaction_date' => $depreciationDate,
                    'reference_number' => $refNumber,
                    'description' => $description,
                    'lines' => [
                        [
                            'chart_of_account_id' => $asset->expense_account_id,
                            'debit' => $amountToDepreciate,
                            'credit' => 0,
                            'memo' => "Beban Penyusutan {$asset->name}",
                        ],
                        [
                            'chart_of_account_id' => $asset->depreciation_account_id,
                            'debit' => 0,
                            'credit' => $amountToDepreciate,
                            'memo' => "Akumulasi Penyusutan {$asset->name}",
                        ],
                    ],
                ]);

                // 5. Catat log ke tabel asset_depreciations
                $depreciationLog = AssetDepreciation::create([
                    'fixed_asset_id' => $asset->id,
                    'depreciation_date' => $depreciationDate,
                    'amount' => $amountToDepreciate,
                    'journal_header_id' => $journal->id,
                ]);

                // Jika setelah penyusutan ini akumulasi mencapai batas, ubah status ke DISPOSED
                if (($accumulated + $amountToDepreciate) >= ($depreciableLimit - 0.0001)) {
                    $asset->update(['status' => 'DISPOSED']);
                }

                $processedCount++;
                $totalDepreciated += $amountToDepreciate;

                $details[] = [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'amount' => $amountToDepreciate,
                    'journal_id' => $journal->id,
                ];
            }

            return [
                'year_month' => $yearMonth,
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'total_amount' => $totalDepreciated,
                'details' => $details,
            ];
        });
    }
}
