<?php

namespace App\Modules\SalesOrder\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Services\SalesOrderService;
use App\Modules\SalesOrder\Resources\SalesOrderResource;
use App\Modules\SalesOrder\Requests\StoreSalesOrderRequest;
use App\Modules\SalesOrder\Requests\UpdateSalesOrderRequest;

class SalesOrderController extends Controller
{
    public function __construct(
        protected SalesOrderService $service
    ) {}

    public function index()
    {
        return SalesOrderResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreSalesOrderRequest $request
    ) {
        return new SalesOrderResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        SalesOrder $salesOrder
    ) {
        return new SalesOrderResource(
            $this->service->find(
                $salesOrder->id
            )
        );
    }

    public function update(
        UpdateSalesOrderRequest $request,
        SalesOrder $salesOrder
    ) {
        return new SalesOrderResource(
            $this->service->update(
                $salesOrder->id,
                $request->validated()
            )
        );
    }

    public function approve(
        SalesOrder $salesOrder
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sales Order approved successfully',
            'data' => new SalesOrderResource(
                $this->service->approve(
                    $salesOrder
                )
            ),
        ]);
    }

    public function convertToSale(
        SalesOrder $salesOrder
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sales Order converted successfully',
            'data' => $this->service->convertToSale(
                $salesOrder
            ),
        ]);
    }

    public function destroy(
        SalesOrder $salesOrder
    ) {
        $this->service->delete(
            $salesOrder->id
        );

        return response()->json([
            'message' => 'Sales Order deleted successfully',
        ]);
    }
}
