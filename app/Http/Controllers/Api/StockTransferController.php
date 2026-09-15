<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $stockTransferService)
    {
    }

    /**
     * List transfers that involve the caller's branch. Master users see everything.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $transfers = StockTransfer::with(['sourceBranch', 'destinationBranch', 'items'])
            ->when(! $user->isMaster(), function ($query) use ($user) {
                $query->where(function ($scoped) use ($user) {
                    $scoped->where('source_branch_id', $user->branch_id)
                        ->orWhere('destination_branch_id', $user->branch_id);
                });
            })
            ->latest('transfer_date')
            ->latest('id')
            ->get();

        return response()->json([
            'message' => 'Stock transfers retrieved successfully.',
            'data' => $transfers,
        ]);
    }

    /**
     * Execute a stock transfer. All stock and ledger mutations run inside a single
     * database transaction inside the service layer.
     */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->stockTransferService->transfer(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'message' => 'Stock transfer completed successfully.',
            'status' => 'success',
            'reference_number' => $transfer->reference_number,
            'data' => $transfer,
        ], 201);
    }

    public function show(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $user = $request->user();

        $involvesUserBranch = in_array(
            (int) $user->branch_id,
            [(int) $stockTransfer->source_branch_id, (int) $stockTransfer->destination_branch_id],
            true,
        );

        if (! $user->isMaster() && ! $involvesUserBranch) {
            return response()->json([
                'message' => 'Unauthorized. This transfer does not involve your branch.',
            ], 403);
        }

        return response()->json([
            'data' => $stockTransfer->load([
                'sourceBranch',
                'destinationBranch',
                'creator',
                'items.sourceProduct',
                'items.destinationProduct',
            ]),
        ]);
    }
}
