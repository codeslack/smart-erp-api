<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Requests\SupplierStatementRequest;
use App\Modules\Accounting\Services\SupplierStatementService;
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
    ) {

        $statement = $this->service
            ->getStatement(
                supplierId: $supplierId,
                fromDate: $request->validated('from_date'),
                toDate: $request->validated('to_date')
            );

        return response()->json([
            'success' => true,
            'message' => 'Supplier Statement fetched successfully',
            'data' => $statement,
        ]);
    }
}