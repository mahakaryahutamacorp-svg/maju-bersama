<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SalePostingService;
use App\Support\DecimalQuantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleCorrectionController extends Controller
{
    /**
     * Rincian nota penjualan. Hanya akun master.
     */
    public function show(Request $request, Sale $sale, SalePostingService $posting): View
    {
        $this->ensureMaster($request);

        $sale->load(['items.product', 'customer', 'branch', 'user']);

        return view('backoffice.reports.sales-show', [
            'sale' => $sale,
            'lockReason' => $sale->correctionLockReason(),
            'stockOrigin' => $posting->stockOrigin($sale),
        ]);
    }

    /**
     * Formulir koreksi nota yang salah. Hanya akun master.
     */
    public function edit(Request $request, Sale $sale, SalePostingService $posting): View
    {
        $this->ensureMaster($request);

        $sale->load(['items.product', 'customer', 'branch']);
        $productIds = $sale->items->pluck('product_id');

        $products = Product::withoutGlobalScopes()
            ->withTrashed()
            ->where(function ($query) use ($sale, $productIds) {
                $query->where(function ($owned) use ($sale) {
                    $owned->where('branch_id', $sale->branch_id)->whereNull('deleted_at');
                });

                if ($productIds->isNotEmpty()) {
                    $query->orWhereIn('id', $productIds);
                }
            })
            ->orderBy('name')
            ->get();

        $initialItems = old('items', $sale->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => DecimalQuantity::present($item->quantity),
            'price' => (float) $item->price,
            'discount_amount' => (float) $item->discount_amount,
        ])->all());

        return view('backoffice.reports.sales-edit', [
            'sale' => $sale,
            'customers' => Customer::withoutGlobalScopes()
                ->where('branch_id', $sale->branch_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'selling_price' => (float) $product->selling_price,
            ])->values(),
            'initialItems' => collect($initialItems)->values(),
            'lockReason' => $sale->correctionLockReason(),
            'stockOrigin' => $posting->stockOrigin($sale),
        ]);
    }

    /**
     * Simpan koreksi. Selisih kuantitas dikembalikan atau dipotong di gudang cabang asal.
     */
    public function update(UpdateSaleRequest $request, Sale $sale, SalePostingService $posting): RedirectResponse
    {
        $updated = $posting->reviseSale($sale, $request->validated(), $request->user());

        return redirect()
            ->route('reports.sales.show', $updated)
            ->with('success', "Nota {$updated->receipt_number} diperbarui. Stok dan jurnal sudah disesuaikan dengan isi nota yang baru.");
    }

    /**
     * Hapus nota yang salah dan kembalikan seluruh stok ke gudang cabang asal.
     */
    public function destroy(Request $request, Sale $sale, SalePostingService $posting): RedirectResponse
    {
        $this->ensureMaster($request);

        $receipt = $sale->receipt_number;
        $posting->voidSale($sale, $request->user());

        return redirect()
            ->route('reports.sales')
            ->with('success', "Nota {$receipt} dihapus. Stok dikembalikan ke gudang cabang asal dan jurnal penjualan dibatalkan.");
    }

    private function ensureMaster(Request $request): void
    {
        if (! $request->user()?->isMaster()) {
            abort(403, 'Hanya akun master yang dapat mengubah atau menghapus transaksi penjualan.');
        }
    }
}
