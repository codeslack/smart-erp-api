<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\PayableSummaryService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class PayableSummaryController extends Controller
{
    public function __construct(
        protected PayableSummaryService $service
    ) {}

    /**
     * PayableSummary
     */
    public function index()
    {
        return response()->json([

            'success' => true,

            'message'
            => 'Payable Summary fetched successfully',

            'data'
            => $this->service
                ->getReport(),
        ]);
    }
}
