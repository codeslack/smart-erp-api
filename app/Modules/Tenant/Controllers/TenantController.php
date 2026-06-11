<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantService;
use App\Modules\Tenant\Resources\TenantResource;
use App\Modules\Tenant\Requests\StoreTenantRequest;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $service
    ) {}

    public function index()
    {
        return TenantResource::collection(
            Tenant::latest()->paginate()
        );
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->service->create(
            $request->validated()
        );

        return new TenantResource($tenant);
    }

    public function show(Tenant $tenant)
    {
        return new TenantResource($tenant);
    }

    public function update(
        StoreTenantRequest $request,
        Tenant $tenant
    ) {
        $tenant->update(
            $request->validated()
        );

        return new TenantResource($tenant);
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted'
        ]);
    }
}
