<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\SupplierAgingService;
use App\Modules\Accounting\Requests\SupplierAgingRequest;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class SupplierAgingController extends Controller
{
    public function __construct(
        protected SupplierAgingService $service
    ) {}

    /**
     * SupplierAging
     */
    public function index(
        SupplierAgingRequest $request
    ): JsonResponse {

        $report = $this->service
            ->getReport(
                $request->validated(
                    'as_of_date'
                )
            );

        return response()->json([

            'success' => true,

            'message'
                => 'Supplier Aging Report fetched successfully',

            'data'
                => $report,
        ]);
    }
}
