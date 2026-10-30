<?php

namespace App\Modules\SalesOrder\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\SalesOrder\Requests\StoreSalesOrderRequest;
use App\Modules\SalesOrder\Requests\UpdateSalesOrderRequest;
use App\Modules\SalesOrder\Resources\SalesOrderItemResource;
use App\Modules\SalesOrder\Resources\SalesOrderResource;
use App\Modules\SalesOrder\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrderController extends ApiController
{
    public function __construct(
        protected SalesOrderService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $salesOrders = $this->service->paginate(
            $request->only([
                'status',
                'customer_id',
                'customer_uuid',
                'warehouse_id',
                'warehouse_uuid',
                'order_number',
                'order_date_from',
                'order_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            SalesOrderResource::collection($salesOrders),
            'Sales orders retrieved successfully.'
        );
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $salesOrder = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new SalesOrderResource($salesOrder),
            'Sales order created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $salesOrder = $this->service->findByUuid($uuid) ?? $this->service->find($uuid);

        return $this->success(
            new SalesOrderResource($salesOrder),
            'Sales order retrieved successfully.'
        );
    }

    public function update(UpdateSalesOrderRequest $request, string $uuid): JsonResponse
    {
        $salesOrder = $this->service->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new SalesOrderResource($salesOrder),
            'Sales order updated successfully.'
        );
    }

    public function confirm(string $uuid): JsonResponse
    {
        $salesOrder = $this->service->confirm($uuid);

        return $this->success(
            new SalesOrderResource($salesOrder),
            'Sales order confirmed successfully.'
        );
    }

    public function approve(string $uuid): JsonResponse
    {
        return $this->confirm($uuid);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $salesOrder = $this->service->cancel(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new SalesOrderResource($salesOrder),
            'Sales order cancelled successfully.'
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->service->delete($uuid);

        return $this->success(
            null,
            'Sales order deleted successfully.'
        );
    }

    public function items(string $uuid): JsonResponse
    {
        $items = $this->service->getItems($uuid);

        return $this->success(
            SalesOrderItemResource::collection($items),
            'Sales order items retrieved successfully.'
        );
    }
}
