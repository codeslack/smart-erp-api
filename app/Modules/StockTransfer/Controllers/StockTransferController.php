<?php

namespace App\Modules\StockTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\StockTransfer\Models\StockTransfer;
use App\Modules\StockTransfer\Services\StockTransferService;
use App\Modules\StockTransfer\Resources\StockTransferResource;
use App\Modules\StockTransfer\Requests\StoreStockTransferRequest;
use App\Modules\StockTransfer\Requests\UpdateStockTransferRequest;

class StockTransferController extends Controller
{
    public function __construct(
        protected StockTransferService $service
    ) {}

    public function index()
    {
        return StockTransferResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreStockTransferRequest $request
    ) {
        return new StockTransferResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        StockTransfer $stockTransfer
    ) {
        return new StockTransferResource(
            $this->service->find(
                $stockTransfer->id
            )
        );
    }

    public function update(
        UpdateStockTransferRequest $request,
        StockTransfer $stockTransfer
    ) {
        return new StockTransferResource(
            $this->service->update(
                $stockTransfer->id,
                $request->validated()
            )
        );
    }

    public function approve(
        StockTransfer $stockTransfer
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Stock Transfer approved successfully',
            'data' => new StockTransferResource(
                $this->service->approve(
                    $stockTransfer
                )
            ),
        ]);
    }

    public function destroy(
        StockTransfer $stockTransfer
    ) {
        $this->service->delete(
            $stockTransfer->id
        );

        return response()->json([
            'message' => 'Stock Transfer deleted successfully',
        ]);
    }
}
