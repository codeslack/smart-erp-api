<?php

namespace App\Modules\SalesReturn\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Services\SalesReturnService;
use App\Modules\SalesReturn\Resources\SalesReturnResource;
use App\Modules\SalesReturn\Requests\StoreSalesReturnRequest;
use App\Modules\SalesReturn\Requests\UpdateSalesReturnRequest;

class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $service
    ) {}

    public function index()
    {
        return SalesReturnResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreSalesReturnRequest $request
    ) {
        return new SalesReturnResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        SalesReturn $salesReturn
    ) {
        return new SalesReturnResource(
            $this->service->find(
                $salesReturn->id
            )
        );
    }

    public function update(
        UpdateSalesReturnRequest $request,
        SalesReturn $salesReturn
    ) {
        return new SalesReturnResource(
            $this->service->update(
                $salesReturn->id,
                $request->validated()
            )
        );
    }

    public function approve(
        SalesReturn $salesReturn
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Sales Return approved successfully',
            'data' => new SalesReturnResource(
                $this->service->approve(
                    $salesReturn
                )
            ),
        ]);
    }

    public function destroy(
        SalesReturn $salesReturn
    ) {
        $this->service->delete(
            $salesReturn->id
        );

        return response()->json([
            'message' => 'Sales Return deleted successfully',
        ]);
    }
}
