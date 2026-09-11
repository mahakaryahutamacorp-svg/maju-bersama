<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $items = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($productItems) => [
                'product_id' => (int) $productItems->first()['product_id'],
                'quantity' => $productItems->sum('quantity'),
            ])
            ->values();

        $sale = DB::transaction(function () use ($items, $request): array {
            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id'))
                ->where('branch_id', $request->user()->branch_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products are not available at your branch.'],
                ]);
            }

            $totalCents = 0;
            $lines = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}."],
                    ]);
                }

                $product->decrement('stock', $item['quantity']);
                $lineTotalCents = $this->toCents($product->selling_price) * $item['quantity'];
                $totalCents += $lineTotalCents;

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotalCents / 100,
                ];
            }

            $accounts = ChartOfAccount::query()
                ->whereIn('code', ['1110', '4110'])
                ->get()
                ->keyBy('code');

            if (! $accounts->has('1110') || ! $accounts->has('4110')) {
                throw ValidationException::withMessages([
                    'items' => ['Required POS ledger accounts are not configured.'],
                ]);
            }

            $journal = JournalHeader::create([
                'branch_id' => $request->user()->branch_id,
                'user_id' => $request->user()->id,
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'POS-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'description' => 'POS Sale',
            ]);

            $total = $totalCents / 100;
            $journal->journalLines()->createMany([
                [
                    'chart_of_account_id' => $accounts['1110']->id,
                    'debit' => $total,
                    'credit' => 0,
                    'memo' => 'POS cash sale',
                ],
                [
                    'chart_of_account_id' => $accounts['4110']->id,
                    'debit' => 0,
                    'credit' => $total,
                    'memo' => 'POS sales revenue',
                ],
            ]);

            return [
                'journal' => $journal->load('journalLines'),
                'items' => $lines,
                'total' => $total,
            ];
        });

        return response()->json([
            'message' => 'Checkout completed successfully.',
            ...$sale,
        ], 201);
    }

    private function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}