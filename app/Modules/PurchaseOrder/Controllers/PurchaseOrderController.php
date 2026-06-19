<?php

namespace App\Modules\PurchaseOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Services\PurchaseOrderService;
use App\Modules\PurchaseOrder\Resources\PurchaseOrderResource;
use App\Modules\PurchaseOrder\Requests\StorePurchaseOrderRequest;
use App\Modules\PurchaseOrder\Requests\UpdatePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $service
    ) {}

    public function index()
    {
        return PurchaseOrderResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StorePurchaseOrderRequest $request
    ) {
        return new PurchaseOrderResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        PurchaseOrder $purchaseOrder
    ) {
        return new PurchaseOrderResource(
            $this->service->find(
                $purchaseOrder->id
            )
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder
    ) {
        return new PurchaseOrderResource(
            $this->service->update(
                $purchaseOrder->id,
                $request->validated()
            )
        );
    }

    public function approve(
        PurchaseOrder $purchaseOrder
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Purchase Order approved successfully',
            'data' => new PurchaseOrderResource(
                $this->service->approve(
                    $purchaseOrder
                )
            ),
        ]);
    }

    public function convertToPurchase(
        PurchaseOrder $purchaseOrder
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Purchase Order converted successfully',
            'data' => $this->service->convertToPurchase(
                $purchaseOrder
            ),
        ]);
    }

    public function destroy(
        PurchaseOrder $purchaseOrder
    ) {
        $this->service->delete(
            $purchaseOrder->id
        );

        return response()->json([
            'message' => 'Purchase Order deleted successfully',
        ]);
    }
}
