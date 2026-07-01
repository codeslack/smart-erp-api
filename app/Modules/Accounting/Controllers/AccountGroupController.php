<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Services\AccountGroupService;
use App\Modules\Accounting\Resources\AccountGroupResource;
use App\Modules\Accounting\Requests\StoreAccountGroupRequest;
use App\Modules\Accounting\Requests\UpdateAccountGroupRequest;

class AccountGroupController extends Controller
{
    public function __construct(
        protected AccountGroupService $service
    ) {}

    public function index()
    {
        return AccountGroupResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreAccountGroupRequest $request
    ) {
        $group = $this->service->create(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Account Group created successfully',
            'data'    => $group,
        ], 201);
    }

    public function show(
        AccountGroup $accountGroup
    ) {
        return response()->json([
            'data' => $accountGroup,
        ]);
    }

    public function update(
        UpdateAccountGroupRequest $request,
        AccountGroup $accountGroup
    ) {
        $group = $this->service->update(
            $accountGroup->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Account Group updated successfully',
            'data'    => $group,
        ]);
    }

    public function destroy(
        AccountGroup $accountGroup
    ) {
        $this->service->delete(
            $accountGroup->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Account Group deleted successfully',
        ]);
    }
}