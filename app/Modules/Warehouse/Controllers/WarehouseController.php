<?php

namespace App\Modules\Warehouse\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Warehouse\Services\WarehouseService;

use App\Modules\Warehouse\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseRequest;

use App\Modules\Warehouse\Resources\WarehouseResource;

class WarehouseController extends ApiController
{
    public function __construct(
        protected WarehouseService $service
    ) {}

    public function index(): JsonResponse
    {
        $warehouses =
            $this->service->paginate();

        return $this->success(

            WarehouseResource::collection(
                $warehouses
            ),

            'Warehouses retrieved successfully.'
        );
    }

    public function store(
        StoreWarehouseRequest $request
    ): JsonResponse {

        $warehouse =
            $this->service->create(
                $request->validated()
            );

        return $this->success(

            new WarehouseResource(
                $warehouse->load('area')
            ),

            'Warehouse created successfully.',
            
            201
        );
    }

    public function show(
        Warehouse $warehouse
    ): JsonResponse {

        return $this->success(

            new WarehouseResource(
                $warehouse->load('area')
            ),

            'Warehouse retrieved successfully.'
        );
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse
    ): JsonResponse {

        $warehouse =
            $this->service->update(
                $warehouse,
                $request->validated()
            );

        return $this->success(

            new WarehouseResource(
                $warehouse->load('area')
            ),

            'Warehouse updated successfully.'
        );
    }

    public function destroy(
        Warehouse $warehouse
    ): JsonResponse {

        $this->service->delete(
            $warehouse
        );

        return $this->success(
            null,
            'Warehouse deleted successfully.'
        );
    }
}