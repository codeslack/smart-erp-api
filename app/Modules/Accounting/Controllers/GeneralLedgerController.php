<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\GeneralLedgerService;
use App\Modules\Accounting\Resources\GeneralLedgerResource;
use App\Modules\Accounting\Requests\GeneralLedgerRequest;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class GeneralLedgerController
    extends Controller
{
    public function __construct(
        protected GeneralLedgerService $service
    ) {}

    /**
     * GeneralLedger
     */
    public function index(
        GeneralLedgerRequest $request
    )
    {
        $ledger = $this->service
            ->getLedger(

                $request->integer(
                    'account_id'
                ),

                $request->input(
                    'from_date'
                ),

                $request->input(
                    'to_date'
                )
            );

        return response()->json([

            'success' => true,

            'message'
                => 'General Ledger fetched successfully',

            'data'
                => new GeneralLedgerResource(
                    $ledger
                ),
        ]);
    }
}
