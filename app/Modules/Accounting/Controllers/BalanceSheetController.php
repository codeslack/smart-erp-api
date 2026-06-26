<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\BalanceSheetService;
use App\Modules\Accounting\Resources\BalanceSheetResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class BalanceSheetController
    extends Controller
{
    public function __construct(
        protected BalanceSheetService $service
    ) {}

    /**
     * BalanceSheet
     */
    public function index(
        Request $request
    )
    {
        $report = $this->service
            ->getBalanceSheet(
                $request->as_of_date
            );

        return response()->json([

            'success' => true,

            'message'
                => 'Balance Sheet fetched successfully',

            'data'
                => new BalanceSheetResource(
                    $report
                ),
        ]);
    }
}