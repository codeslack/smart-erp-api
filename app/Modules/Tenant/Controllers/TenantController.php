<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Requests\StoreTenantRequest;
use App\Modules\Tenant\Resources\TenantResource;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantController extends ApiController
{
    public function __construct(
        protected TenantRepositoryInterface $tenants,
        protected TenantService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        $tenants = $this->tenants->paginate();

        return $this->success(
            TenantResource::collection(
                $tenants
            )
        );
    }

    public function store(
        StoreTenantRequest $request
    ): JsonResponse {

        $tenant = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new TenantResource(
                $tenant
            ),
            'Company created successfully.',
            201
        );
    }

    public function show(
        Tenant $tenant
    ): JsonResponse {

        return $this->success(
            new TenantResource(
                $tenant
            )
        );
    }
}