<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Sales\Requests\StoreSaleRequest;
use App\Modules\Sales\Requests\UpdateSaleRequest;
use App\Modules\Sales\Resources\SaleItemResource;
use App\Modules\Sales\Resources\SaleResource;
use App\Modules\Sales\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    public function __construct(
        protected SaleService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sales = $this->service->paginate(
            $request->only([
                'status',
                'customer_id',
                'customer_uuid',
                'warehouse_id',
                'warehouse_uuid',
                'sale_no',
                'sale_date_from',
                'sale_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            SaleResource::collection($sales),
            'Sales retrieved successfully.'
        );
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new SaleResource($sale),
            'Sale created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $sale = $this->service->findByUuid($uuid) ?? $this->service->find($uuid);

        return $this->success(
            new SaleResource($sale),
            'Sale retrieved successfully.'
        );
    }

    public function update(UpdateSaleRequest $request, string $uuid): JsonResponse
    {
        $sale = $this->service->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new SaleResource($sale),
            'Sale updated successfully.'
        );
    }

    public function confirm(string $uuid): JsonResponse
    {
        $sale = $this->service->confirm($uuid);

        return $this->success(
            new SaleResource($sale),
            'Sale confirmed successfully.'
        );
    }

    public function approve(string $uuid): JsonResponse
    {
        return $this->confirm($uuid);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $sale = $this->service->cancel(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new SaleResource($sale),
            'Sale cancelled successfully.'
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->service->delete($uuid);

        return $this->success(
            null,
            'Sale deleted successfully.'
        );
    }

    public function items(string $uuid): JsonResponse
    {
        $items = $this->service->getItems($uuid);

        return $this->success(
            SaleItemResource::collection($items),
            'Sale items retrieved successfully.'
        );
    }
}
