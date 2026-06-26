<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\ProfitLossService;
use App\Modules\Accounting\Resources\ProfitLossResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class ProfitLossController extends Controller
{
    public function __construct(
        protected ProfitLossService $service
    ) {}

    /**
     * ProfitLoss
     */
    public function index(
        Request $request
    ) {
        $report = $this->service
            ->getProfitLoss(
                $request->from_date,
                $request->to_date
            );

        return response()->json([

            'success' => true,

            'message'
            => 'Profit & Loss fetched successfully',

            'data'
            => new ProfitLossResource(
                $report
            ),
        ]);
    }
}
