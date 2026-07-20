<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleService;
use App\Modules\Sales\Resources\SaleResource;
use App\Modules\Sales\Requests\StoreSaleRequest;
use App\Modules\Sales\Requests\UpdateSaleRequest;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $service
    ) {}

    public function index()
    {
        return SaleResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreSaleRequest $request
    ) {
        return new SaleResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        Sale $sale
    ) {
        return new SaleResource(
            $sale->load([
                'customer',
                'items.product',
                'items.warehouse',
                'advanceAllocations.source'
            ])
        );
    }

    public function update(
        UpdateSaleRequest $request,
        Sale $sale
    ) {
        return new SaleResource(
            $this->service->update(
                $sale->id,
                $request->validated()
            )
        );
    }

    public function approve(
        Sale $sale
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sale approved successfully',
            'data' => new SaleResource(
                $this->service->approve(
                    $sale
                )
            ),
        ]);
    }

    public function destroy(
        Sale $sale
    ) {
        $this->service->delete(
            $sale->id
        );

        return response()->json([
            'message' => 'Sale deleted successfully',
        ]);
    }
}
