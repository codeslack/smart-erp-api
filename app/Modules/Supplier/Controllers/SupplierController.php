<?php

namespace App\Modules\Supplier\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Services\SupplierService;
use App\Modules\Supplier\Resources\SupplierResource;
use App\Modules\Supplier\Requests\StoreSupplierRequest;
use App\Modules\Supplier\Requests\UpdateSupplierRequest;

class SupplierController extends ApiController
{
    public function __construct(
        protected SupplierService $service
    ) {}

    public function index()
    {
        $suppliers = $this->service->paginate();

        return $this->success(
            SupplierResource::collection($suppliers),
            'Suppliers retrieved successfully.'
        );
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new SupplierResource($supplier),
            'Supplier created successfully.',
            201
        );
    }

    public function show(Supplier $supplier)
    {
        $supplier = $this->service->findByUuid(
            $supplier->uuid
        );

        return $this->success(
            new SupplierResource($supplier),
            'Supplier retrieved successfully.'
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier
    ) {
        $supplier = $this->service->update(
            $supplier,
            $request->validated()
        );

        return $this->success(
            new SupplierResource($supplier),
            'Supplier updated successfully.'
        );
    }

    public function destroy(Supplier $supplier)
    {
        $this->service->delete($supplier);

        return $this->success(
            null,
            'Supplier deleted successfully.'
        );
    }
}