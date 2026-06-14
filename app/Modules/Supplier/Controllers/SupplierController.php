<?php

namespace App\Modules\Supplier\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Services\SupplierService;
use App\Modules\Supplier\Resources\SupplierResource;
use App\Modules\Supplier\Requests\StoreSupplierRequest;
use App\Modules\Supplier\Requests\UpdateSupplierRequest;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $service
    ) {}

    public function index()
    {
        return SupplierResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreSupplierRequest $request
    ) {
        return new SupplierResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        Supplier $supplier
    ) {
        return new SupplierResource(
            $this->service->find(
                $supplier->id
            )
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier
    ) {
        return new SupplierResource(
            $this->service->update(
                $supplier->id,
                $request->validated()
            )
        );
    }

    public function destroy(
        Supplier $supplier
    ) {
        $this->service->delete(
            $supplier->id
        );

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ]);
    }
}
