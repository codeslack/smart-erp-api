<?php

namespace App\Modules\SalesReturn\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\SalesReturn\Requests\StoreSalesReturnRequest;
use App\Modules\SalesReturn\Requests\UpdateSalesReturnRequest;
use App\Modules\SalesReturn\Resources\SalesReturnItemResource;
use App\Modules\SalesReturn\Resources\SalesReturnResource;
use App\Modules\SalesReturn\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReturnController extends ApiController
{
    public function __construct(
        protected SalesReturnService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $salesReturns = $this->service->paginate(
            $request->only([
                'status',
                'customer_id',
                'customer_uuid',
                'sale_id',
                'sale_uuid',
                'return_no',
                'return_date_from',
                'return_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            SalesReturnResource::collection($salesReturns),
            'Sales returns retrieved successfully.'
        );
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $salesReturn = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new SalesReturnResource($salesReturn),
            'Sales return created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $salesReturn = $this->service->findByUuid($uuid) ?? $this->service->find($uuid);

        return $this->success(
            new SalesReturnResource($salesReturn),
            'Sales return retrieved successfully.'
        );
    }

    public function update(UpdateSalesReturnRequest $request, string $uuid): JsonResponse
    {
        $salesReturn = $this->service->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new SalesReturnResource($salesReturn),
            'Sales return updated successfully.'
        );
    }

    public function confirm(string $uuid): JsonResponse
    {
        $salesReturn = $this->service->confirm($uuid);

        return $this->success(
            new SalesReturnResource($salesReturn),
            'Sales return confirmed successfully.'
        );
    }

    public function approve(string $uuid): JsonResponse
    {
        return $this->confirm($uuid);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $salesReturn = $this->service->cancel(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new SalesReturnResource($salesReturn),
            'Sales return cancelled successfully.'
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->service->delete($uuid);

        return $this->success(
            null,
            'Sales return deleted successfully.'
        );
    }

    public function items(string $uuid): JsonResponse
    {
        $items = $this->service->getItems($uuid);

        return $this->success(
            SalesReturnItemResource::collection($items),
            'Sales return items retrieved successfully.'
        );
    }
}
