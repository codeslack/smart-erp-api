<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\ChartOfAccountService;
use App\Modules\Accounting\Requests\StoreChartOfAccountRequest;
use App\Modules\Accounting\Requests\UpdateChartOfAccountRequest;

class ChartOfAccountController extends Controller
{
    public function __construct(
        protected ChartOfAccountService $service
    ) {}

    public function index()
    {
        return response()->json(
            $this->service->getAll()
        );
    }

    public function store(
        StoreChartOfAccountRequest $request
    ) {
        $account = $this->service->create(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data'    => $account,
        ], 201);
    }

    public function show(
        ChartOfAccount $chartOfAccount
    ) {
        return response()->json([
            'data' => $this->service->find(
                $chartOfAccount->id
            ),
        ]);
    }

    public function update(
        UpdateChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount
    ) {
        $account = $this->service->update(
            $chartOfAccount->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully',
            'data'    => $account,
        ]);
    }

    public function destroy(
        ChartOfAccount $chartOfAccount
    ) {
        $this->service->delete(
            $chartOfAccount->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}