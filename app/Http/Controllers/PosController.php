<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CashRegisterShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected CashRegisterShiftService $shiftService
    ) {}

    /**
     * Display the POS cashier screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Periksa apakah kasir memiliki shift yang sedang aktif
        $activeShift = CashRegisterShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->with(['cashRegister', 'sales'])
            ->latest('opened_at')
            ->first();

        // Ambil daftar register aktif untuk cabang kasir
        $registers = CashRegister::where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Fallback jika belum ada register di cabang kasir
        if ($registers->isEmpty()) {
            $defaultRegister = CashRegister::create([
                'branch_id' => $user->branch_id,
                'name'      => 'Kasir Utama',
                'is_active' => true,
            ]);
            $registers = collect([$defaultRegister]);
        }

        // Kalkulasi dinamis expected closing balance untuk modal tutup shift
        $expectedBalance = $activeShift
            ? $this->shiftService->calculateExpectedBalance($activeShift)
            : 0;

        $products = Product::with(['category', 'productPrices'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $basePrice = (float) $product->selling_price;
                $product->base_price = $basePrice;
                $product->original_selling_price = $basePrice;
                $product->price = $basePrice;
                return $product;
            });

        $categories = Category::orderBy('name')->get();

        $customers = \App\Models\Customer::with('customerGroup')
            ->orderBy('name')
            ->get();

        $token = $user->createToken('pos_preview')->plainTextToken;

        $viewName = view()->exists('pos.index') ? 'pos.index' : 'pos';

        return view($viewName, [
            'products'        => $products,
            'categories'      => $categories,
            'customers'       => $customers,
            'previewToken'    => $token,
            'currentUser'     => $user,
            'activeShift'     => $activeShift,
            'hasActiveShift'  => $activeShift !== null,
            'registers'       => $registers,
            'expectedBalance' => $expectedBalance,
        ]);
    }

    /**
     * Buka shift kasir baru dari modal pemblokir POS.
     */
    public function openShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'opening_balance'  => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $shift = $this->shiftService->openShift(
            $validated['cash_register_id'],
            $request->user()->id,
            $validated['opening_balance'],
            $validated['notes'] ?? null
        );

        $formatted = 'Rp ' . number_format($shift->opening_balance, 0, ',', '.');

        return redirect()
            ->route('pos')
            ->with('success', "Shift kasir berhasil dibuka dengan modal awal {$formatted}.");
    }

    /**
     * Tutup shift kasir dari tombol navigasi POS.
     */
    public function closeShift(Request $request): RedirectResponse
    {
        $activeShift = CashRegisterShift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if (! $activeShift) {
            return redirect()
                ->route('pos')
                ->with('error', 'Tidak ada sesi shift kasir aktif yang dapat ditutup.');
        }

        $validated = $request->validate([
            'actual_closing_balance' => ['required', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $this->shiftService->closeShift(
            $activeShift,
            $validated['actual_closing_balance'],
            $validated['notes'] ?? null
        );

        $actualFormatted = 'Rp ' . number_format($shift->actual_closing_balance, 0, ',', '.');
        $diffFormatted = 'Rp ' . number_format(abs($shift->difference), 0, ',', '.');
        $diffLabel = $shift->difference > 0 ? "Surplus {$diffFormatted}" : ($shift->difference < 0 ? "Defisit {$diffFormatted}" : "Seimbang (Rp 0)");

        return redirect()
            ->route('pos')
            ->with('success', "Shift kasir berhasil ditutup. Total fisik: {$actualFormatted} ({$diffLabel}).");
    }

    /**
     * Display thermal receipt for a completed sale.
     */
    public function receipt(Request $request, string $receiptNumber): View
    {
        $sale = Sale::with(['branch', 'creator', 'items.product'])
            ->where('receipt_number', $receiptNumber)
            ->firstOrFail();

        $cashTendered = $request->query('cash');
        $changeDue = $request->query('change');

        return view('pos.receipt', [
            'sale'         => $sale,
            'cashTendered' => $cashTendered ? (float) $cashTendered : null,
            'changeDue'    => $changeDue ? (float) $changeDue : null,
        ]);
    }
}