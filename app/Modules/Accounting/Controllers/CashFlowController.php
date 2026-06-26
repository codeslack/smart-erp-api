<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\CashFlowService;
use App\Modules\Accounting\Requests\CashFlowRequest;
use App\Modules\Accounting\Resources\CashFlowResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class CashFlowController extends Controller
{
    public function __construct(
        protected CashFlowService $service
    ) {}

    /**
     * CashFlow
     */
    public function index(
        CashFlowRequest $request
    ) {

        $data = $this->service
            ->getCashFlow(
                $request->from_date,
                $request->to_date
            );

        return response()->json([

            'success' => true,

            'message'
                => 'Cash Flow fetched successfully',

            'data'
                => new CashFlowResource(
                    $data
                ),
        ]);
    }
}