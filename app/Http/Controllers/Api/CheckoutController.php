<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Services\SalePostingService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly SalePostingService $salePostingService)
    {
    }

    /**
     * Record a point of sale transaction.
     *
     * Stock movement, sale records and the four line double entry journal are
     * committed as a single unit of work inside the service layer.
     */
    public function store(StoreCheckoutRequest $request): JsonResponse
    {
        $sale = $this->salePostingService->post(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'message' => 'Checkout completed successfully.',
            'status' => 'success',
            'receipt_number' => $sale->receipt_number,
            'sale' => $sale,
        ], 201);
    }
}
