<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\ChartOfAccountService;
use App\Modules\Accounting\Resources\ChartOfAccountResource;
use App\Modules\Accounting\Requests\StoreChartOfAccountRequest;
use App\Modules\Accounting\Requests\UpdateChartOfAccountRequest;

class ChartOfAccountController extends ApiController
{
    public function __construct(
        protected ChartOfAccountService $service
    ) {}

    public function index()
    {
        return $this->success(
            ChartOfAccountResource::collection(
                $this->service->getAll()
            ),
            'Chart of Accounts retrieved successfully'
        );
    }

    public function store(
        StoreChartOfAccountRequest $request
    ) {
        $account = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new ChartOfAccountResource($account),
            'Account created successfully',
            201
        );
    }

    public function show(
        ChartOfAccount $chartOfAccount
    ) {
        return $this->success(
            new ChartOfAccountResource(
                $this->service->find($chartOfAccount->id)
            ),
            'Account retrieved successfully'
        );
    }

    public function update(
        UpdateChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount
    ) {
        $account = $this->service->update(
            $chartOfAccount->id,
            $request->validated()
        );

        return $this->success(
            new ChartOfAccountResource($account),
            'Account updated successfully'
        );
    }

    public function destroy(
        ChartOfAccount $chartOfAccount
    ) {
        $this->service->delete(
            $chartOfAccount->id
        );

        return $this->success(
            null,
            'Account deleted successfully'
        );
    }
}
