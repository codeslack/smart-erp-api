<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Requests\SupplierStatementRequest;
use App\Modules\Accounting\Services\SupplierStatementService;
use App\Modules\Accounting\Resources\SupplierStatementResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class SupplierStatementController
    extends Controller
{
    public function __construct(
        protected SupplierStatementService $service
    ) {}

    /**
     * SupplierStatement
     */
    public function show(
        SupplierStatementRequest $request,
        int $supplierId
    ): JsonResponse {

        $statement  = 
            $this->service->statement(
                supplierId: $supplierId,
                fromDate: $request->validated('from_date'),
                toDate: $request->validated('to_date')
            );

        return response()->json([
            'success' => true,
            'message' => 'Supplier Statement fetched successfully',
            'data' => [
                'supplier' =>
                    $statement['supplier'],

                'opening_balance' =>
                    $statement['opening_balance'],

                'outstanding_balance' =>
                    $statement['outstanding_balance'],

                'transactions' =>
                    SupplierStatementResource::collection(
                        collect(
                            $statement['transactions']
                        )
                    ),
            ],
        ]);
    }
}