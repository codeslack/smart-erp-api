<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Requests\CustomerStatementRequest;
use App\Modules\Accounting\Services\CustomerStatementService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class CustomerStatementController
    extends Controller
{
    public function __construct(
        protected CustomerStatementService $service
    ) {}

    /**
     * CustomerStatement
     */
    public function show(
        CustomerStatementRequest $request,
        int $customerId
    ) {

        $statement = $this->service
            ->getStatement(
                customerId: $customerId,
                fromDate: $request->validated('from_date'),
                toDate: $request->validated('to_date')
            );

        return response()->json([

            'success' => true,

            'message'
                => 'Customer Statement fetched successfully',

            'data'
                => $statement,
        ]);
    }
}