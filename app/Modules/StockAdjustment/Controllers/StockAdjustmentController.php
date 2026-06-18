<?php

namespace App\Modules\StockAdjustment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\StockAdjustment\Models\StockAdjustment;
use App\Modules\StockAdjustment\Services\StockAdjustmentService;
use App\Modules\StockAdjustment\Resources\StockAdjustmentResource;
use App\Modules\StockAdjustment\Requests\StoreStockAdjustmentRequest;
use App\Modules\StockAdjustment\Requests\UpdateStockAdjustmentRequest;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected StockAdjustmentService $service
    ) {}

    public function index()
    {
        return StockAdjustmentResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreStockAdjustmentRequest $request
    ) {
        return new StockAdjustmentResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        StockAdjustment $stockAdjustment
    ) {
        return new StockAdjustmentResource(
            $this->service->find(
                $stockAdjustment->id
            )
        );
    }

    public function update(
        UpdateStockAdjustmentRequest $request,
        StockAdjustment $stockAdjustment
    ) {
        return new StockAdjustmentResource(
            $this->service->update(
                $stockAdjustment->id,
                $request->validated()
            )
        );
    }

    public function approve(
        StockAdjustment $stockAdjustment
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Stock Adjustment approved successfully',
            'data' => new StockAdjustmentResource(
                $this->service->approve(
                    $stockAdjustment
                )
            ),
        ]);
    }

    public function destroy(
        StockAdjustment $stockAdjustment
    ) {
        $this->service->delete(
            $stockAdjustment->id
        );

        return response()->json([
            'message' => 'Stock Adjustment deleted successfully',
        ]);
    }
}
