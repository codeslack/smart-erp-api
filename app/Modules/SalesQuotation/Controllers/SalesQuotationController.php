<?php

namespace App\Modules\SalesQuotation\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\SalesOrder\Resources\SalesOrderResource;
use App\Modules\SalesQuotation\Requests\StoreSalesQuotationRequest;
use App\Modules\SalesQuotation\Requests\UpdateSalesQuotationRequest;
use App\Modules\SalesQuotation\Resources\SalesQuotationItemResource;
use App\Modules\SalesQuotation\Resources\SalesQuotationResource;
use App\Modules\SalesQuotation\Services\SalesQuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesQuotationController extends ApiController
{
    public function __construct(
        protected SalesQuotationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $quotations = $this->service->paginate(
            $request->only([
                'status',
                'customer_id',
                'customer_uuid',
                'warehouse_id',
                'warehouse_uuid',
                'quotation_no',
                'quotation_date_from',
                'quotation_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            SalesQuotationResource::collection($quotations),
            'Sales quotations retrieved successfully.'
        );
    }

    public function store(StoreSalesQuotationRequest $request): JsonResponse
    {
        $quotation = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $quotation = $this->service->findByUuid($uuid) ?? $this->service->find($uuid);

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation retrieved successfully.'
        );
    }

    public function update(UpdateSalesQuotationRequest $request, string $uuid): JsonResponse
    {
        $quotation = $this->service->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation updated successfully.'
        );
    }

    public function send(string $uuid): JsonResponse
    {
        $quotation = $this->service->send($uuid);

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation marked as sent successfully.'
        );
    }

    public function accept(string $uuid): JsonResponse
    {
        $quotation = $this->service->accept($uuid);

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation accepted successfully.'
        );
    }

    public function approve(string $uuid): JsonResponse
    {
        return $this->accept($uuid);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $quotation = $this->service->reject(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation rejected successfully.'
        );
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $quotation = $this->service->cancel(
            $uuid,
            $request->input('reason')
        );

        return $this->success(
            new SalesQuotationResource($quotation),
            'Sales quotation cancelled successfully.'
        );
    }

    public function convertToOrder(Request $request, string $uuid): JsonResponse
    {
        $order = $this->service->convertToOrder(
            $uuid,
            $request->all()
        );

        return $this->success(
            new SalesOrderResource($order),
            'Sales quotation converted to Sales Order successfully.'
        );
    }

    public function convertToSalesOrder(Request $request, string $uuid): JsonResponse
    {
        return $this->convertToOrder($request, $uuid);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->service->delete($uuid);

        return $this->success(
            null,
            'Sales quotation deleted successfully.'
        );
    }

    public function items(string $uuid): JsonResponse
    {
        $items = $this->service->getItems($uuid);

        return $this->success(
            SalesQuotationItemResource::collection($items),
            'Sales quotation items retrieved successfully.'
        );
    }
}
