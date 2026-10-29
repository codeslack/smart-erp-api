<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Accounting\Services\GeneralLedgerService;
use App\Modules\Accounting\Resources\GeneralLedgerResource;
use App\Modules\Accounting\Requests\GeneralLedgerRequest;
use App\Modules\Accounting\Models\ChartOfAccount;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class GeneralLedgerController extends ApiController
{
    public function __construct(
        protected GeneralLedgerService $service
    ) {}

    /**
     * GeneralLedger
     */
    public function index(
        GeneralLedgerRequest $request
    ) {
        $accountId = $request->integer('account_id');

        if (! $accountId && $request->filled('account_uuid')) {
            $account = ChartOfAccount::query()
                ->where('uuid', $request->input('account_uuid'))
                ->firstOrFail();

            $accountId = $account->id;
        }

        $ledger = $this->service->getLedger(
            $accountId,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return response()->json([
            'success' => true,
            'message' => 'General Ledger fetched successfully',
            'data' => new GeneralLedgerResource($ledger),
        ]);
    }
}
