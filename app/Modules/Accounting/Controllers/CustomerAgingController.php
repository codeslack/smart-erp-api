<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\CustomerAgingService;
use App\Modules\Accounting\Requests\CustomerAgingRequest;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class CustomerAgingController extends Controller
{
    public function __construct(
        protected CustomerAgingService $service
    ) {}

    /**
     * CustomerAging
     */
    public function index(
        CustomerAgingRequest $request
    ): JsonResponse {

        $report = $this->service
            ->getReport(
                $request->validated('as_of_date')
            );

        return response()->json([

            'success' => true,

            'message'
                => 'Customer Aging Report fetched successfully',

            'data'
                => $report,
        ]);
    }
}
