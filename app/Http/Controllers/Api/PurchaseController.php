<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    private const ACCOUNT_INVENTORY = '1210';
    private const ACCOUNT_PAYABLE = '2110';

    /**
     * Display a listing of purchases.
     */
    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['distributor', 'branch', 'payments'])
            ->latest('id')
            ->get();

        return response()->json([
            'message' => 'Purchases retrieved successfully.',
            'data' => $purchases,
        ]);
    }

    /**
     * Store a new purchase from distributor (creates AP invoice and initial journal).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', 'unique:purchases,invoice_number'],
            'distributor_id' => ['nullable', 'integer'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $branchId = $user->branch_id ?? 1;

        $purchase = DB::transaction(function () use ($validated, $user, $branchId): Purchase {
            $purchase = Purchase::create([
                'branch_id' => $branchId,
                'distributor_id' => $validated['distributor_id'] ?? null,
                'invoice_number' => $validated['invoice_number'],
                'total_amount' => $validated['total_amount'],
                'paid_amount' => 0.00,
                'status' => 'unpaid',
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Ensure required accounts exist
            $inventoryAccount = ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_INVENTORY],
                ['name' => 'Persediaan', 'type' => 'asset']
            );

            $payableAccount = ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_PAYABLE],
                ['name' => 'Hutang Dagang', 'type' => 'liability']
            );

            // Record Initial Purchase on Credit Journal:
            // Dr. Persediaan (1210)
            //   Cr. Hutang Dagang (2110)
            $journal = JournalHeader::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'transaction_date' => now()->toDateString(),
                'reference_number' => $purchase->invoice_number,
                'description' => 'Pembelian Barang dari Distributor (Kredit) - ' . $purchase->invoice_number,
            ]);

            $totalAmountFormatted = number_format((float) $purchase->total_amount, 2, '.', '');

            $journal->journalLines()->createMany([
                [
                    'chart_of_account_id' => $inventoryAccount->id,
                    'debit' => $totalAmountFormatted,
                    'credit' => '0.00',
                    'memo' => 'Persediaan masuk pembelian ' . $purchase->invoice_number,
                ],
                [
                    'chart_of_account_id' => $payableAccount->id,
                    'debit' => '0.00',
                    'credit' => $totalAmountFormatted,
                    'memo' => 'Hutang dagang pembelian ' . $purchase->invoice_number,
                ],
            ]);

            return $purchase->load('payments');
        });

        return response()->json([
            'message' => 'Purchase created successfully and AP invoice recorded.',
            'status' => 'success',
            'data' => $purchase,
        ], 201);
    }

    /**
     * Display the specified purchase with payments and remaining debt.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json([
            'data' => $purchase->load(['payments', 'distributor', 'branch']),
        ]);
    }
}
