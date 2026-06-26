<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\ReceivableSummaryService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class ReceivableSummaryController extends Controller
{
    public function __construct(
        protected ReceivableSummaryService $service
    ) {}

    /**
     * ReceivableSummary
     */
    public function index()
    {
        return response()->json([

            'success' => true,

            'message'
                => 'Receivable Summary fetched successfully',

            'data'
                => $this->service
                    ->getReport(),
        ]);
    }
}