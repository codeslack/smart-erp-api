<?php

namespace App\Modules\Purchase\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Purchase\Resources\PurchaseResource;
use App\Modules\Purchase\Requests\StorePurchaseRequest;
use App\Modules\Purchase\Requests\UpdatePurchaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends ApiController
{
    public function __construct(
        protected PurchaseService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = $this->service->paginate(
            $request->only([
                'status',
                'supplier_id',
                'supplier_uuid',
                'warehouse_id',
                'warehouse_uuid',
                'purchase_no',
                'purchase_date_from',
                'purchase_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            PurchaseResource::collection($purchases),
            'Purchases retrieved successfully.'
        );
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new PurchaseResource($purchase),
            'Purchase created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $purchase = $this->service->findByUuidOrFail($uuid);

        $purchase->load([
            'supplier',
            'warehouse',
            'purchaseOrder',
            'goodsReceiptNote',
            'paymentTerm',
            'items.product',
            'items.variant',
            'items.warehouse',
            'items.unit',
            'items.tax',
            'items.batches',
            'items.serials',
            'charges.chartOfAccount',
            'charges.tax',
            'advanceAllocations.source',
            'paymentAllocations',
        ]);

        return $this->success(
            new PurchaseResource($purchase),
            'Purchase retrieved successfully.'
        );
    }

    public function update(UpdatePurchaseRequest $request, string $uuid): JsonResponse
    {
        $purchase = $this->service->findByUuidOrFail($uuid);

        $updated = $this->service->update(
            $purchase,
            $request->validated()
        );

        return $this->success(
            new PurchaseResource($updated),
            'Purchase updated successfully.'
        );
    }

    public function confirm(string $uuid): JsonResponse
    {
        $purchase = $this->service->findByUuidOrFail($uuid);

        $confirmed = $this->service->confirm($purchase);

        return $this->success(
            new PurchaseResource($confirmed),
            'Purchase confirmed successfully.'
        );
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $purchase = $this->service->findByUuidOrFail($uuid);

        $cancelled = $this->service->cancel(
            $purchase,
            $request->input('cancellation_reason')
        );

        return $this->success(
            new PurchaseResource($cancelled),
            'Purchase cancelled successfully.'
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $purchase = $this->service->findByUuidOrFail($uuid);

        $this->service->delete($purchase);

        return $this->success(
            null,
            'Purchase deleted successfully.'
        );
    }
}
