<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Services\AccountGroupService;
use App\Modules\Accounting\Resources\AccountGroupResource;
use App\Modules\Accounting\Requests\StoreAccountGroupRequest;
use App\Modules\Accounting\Requests\UpdateAccountGroupRequest;

class AccountGroupController extends ApiController
{
    public function __construct(
        protected AccountGroupService $service
    ) {}

    public function index()
    {
        return $this->success(
            AccountGroupResource::collection(
                $this->service->getAll()
            ),
            'Account Groups retrieved successfully'
        );
    }

    public function store(
        StoreAccountGroupRequest $request
    ) {
        $group = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new AccountGroupResource($group),
            'Account Group created successfully',
            201
        );
    }

    public function show(
        AccountGroup $accountGroup
    ) {
        return $this->success(
            new AccountGroupResource($accountGroup),
            'Account Group retrieved successfully'
        );
    }

    public function update(
        UpdateAccountGroupRequest $request,
        AccountGroup $accountGroup
    ) {
        $group = $this->service->update(
            $accountGroup->id,
            $request->validated()
        );

        return $this->success(
            new AccountGroupResource($group),
            'Account Group updated successfully'
        );
    }

    public function destroy(
        AccountGroup $accountGroup
    ) {
        $this->service->delete(
            $accountGroup->id
        );

        return $this->success(
            null,
            'Account Group deleted successfully'
        );
    }
}
