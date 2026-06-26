<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\TrialBalanceService;
use App\Modules\Accounting\Resources\TrialBalanceResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class TrialBalanceController
    extends Controller
{
    public function __construct(
        protected TrialBalanceService $service
    ) {}

    /**
     * TrialBalance
     */
    public function index()
    {
        return response()->json([

            'success' => true,

            'message'
                => 'Trial Balance fetched successfully',

            'data'
                => new TrialBalanceResource(
                    $this->service
                        ->getTrialBalance()
                ),
        ]);
    }
}
