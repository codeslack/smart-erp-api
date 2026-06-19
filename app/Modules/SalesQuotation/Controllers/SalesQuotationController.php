<?php

namespace App\Modules\SalesQuotation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\SalesQuotationService;
use App\Modules\SalesQuotation\Resources\SalesQuotationResource;
use App\Modules\SalesQuotation\Requests\StoreSalesQuotationRequest;
use App\Modules\SalesQuotation\Requests\UpdateSalesQuotationRequest;

class SalesQuotationController extends Controller
{
    public function __construct(
        protected SalesQuotationService $service
    ) {}

    public function index()
    {
        return SalesQuotationResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreSalesQuotationRequest $request
    ) {
        return new SalesQuotationResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        SalesQuotation $salesQuotation
    ) {
        return new SalesQuotationResource(
            $this->service->find(
                $salesQuotation->id
            )
        );
    }

    public function update(
        UpdateSalesQuotationRequest $request,
        SalesQuotation $salesQuotation
    ) {
        return new SalesQuotationResource(
            $this->service->update(
                $salesQuotation->id,
                $request->validated()
            )
        );
    }

    public function approve(
        SalesQuotation $salesQuotation
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sales Quotation approved successfully',
            'data' => new SalesQuotationResource(
                $this->service->approve(
                    $salesQuotation
                )
            ),
        ]);
    }

    public function convertToSale(
        SalesQuotation $salesQuotation
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sales Quotation converted successfully',
            'data' => $this->service->convertToSale(
                $salesQuotation
            ),
        ]);
    }

    public function destroy(
        SalesQuotation $salesQuotation
    ) {
        $this->service->delete(
            $salesQuotation->id
        );

        return response()->json([
            'message' => 'Sales Quotation deleted successfully',
        ]);
    }
}
