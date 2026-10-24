<?php

namespace App\Modules\PurchaseOrder\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\PurchaseOrder\Services\PurchaseOrderService;

use App\Modules\PurchaseOrder\Resources\PurchaseOrderResource;
use App\Modules\PurchaseOrder\Resources\PurchaseOrderItemResource;

use App\Modules\PurchaseOrder\Requests\StorePurchaseOrderRequest;
use App\Modules\PurchaseOrder\Requests\UpdatePurchaseOrderRequest;

class PurchaseOrderController extends ApiController
{
    public function __construct(
        private readonly PurchaseOrderService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $purchaseOrders = $this->service->paginate(
            $request->only([
                'status',
                'supplier_id',
                'warehouse_id',
                'order_number',
                'order_date_from',
                'order_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            PurchaseOrderResource::collection($purchaseOrders),
            'Purchase orders retrieved successfully.'
        );
    }

    public function store(
        StorePurchaseOrderRequest $request
    ): JsonResponse {
        $purchaseOrder = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order created successfully.',
            201
        );
    }

    public function show(
        string $uuid
    ): JsonResponse {
        $purchaseOrder = $this->service->findByUuid($uuid);

        if (!$purchaseOrder) {
            return $this->error(
                'Purchase order not found.',
                null,
                404
            );
        }

        return $this->success(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order retrieved successfully.'
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        string $uuid
    ): JsonResponse {
        $purchaseOrder = $this->service->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order updated successfully.'
        );
    }

    public function confirm(
        string $uuid
    ): JsonResponse {
        $purchaseOrder = $this->service->confirm($uuid);

        return $this->success(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order confirmed successfully.'
        );
    }

    public function cancel(
        Request $request,
        string $uuid
    ): JsonResponse {
        $purchaseOrder = $this->service->cancel(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order cancelled successfully.'
        );
    }

    public function destroy(
        string $uuid
    ): JsonResponse {
        $this->service->delete($uuid);

        return $this->success(
            null,
            'Purchase order deleted successfully.'
        );
    }

    public function items(
        string $uuid
    ): JsonResponse {
        $items = $this->service->getItems($uuid);

        return $this->success(
            PurchaseOrderItemResource::collection($items),
            'Purchase order items retrieved successfully.'
        );
    }
}