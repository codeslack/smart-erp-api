<?php

namespace App\Modules\ProductUnit\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\ProductUnit\Models\ProductUnit;

use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\ProductUnit\Resources\ProductUnitResource;

use App\Modules\ProductUnit\Requests\StoreProductUnitRequest;
use App\Modules\ProductUnit\Requests\UpdateProductUnitRequest;

class ProductUnitController extends ApiController
{
    public function __construct(
        protected ProductUnitService $service
    ) {}

    public function index()
    {
        $productId = request()->integer('product_id');

        $productUnits = $this->service
            ->getByProductId($productId);

        return $this->success(
            ProductUnitResource::collection(
                $productUnits
            ),
            'Product units retrieved successfully.'
        );
    }

    public function store(
        StoreProductUnitRequest $request
    )
    {
        $productUnit = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new ProductUnitResource(
                $productUnit->load('unit')
            ),
            'Product unit created successfully.',
            201
        );
    }

    public function show(
        ProductUnit $productUnit
    )
    {
        return $this->success(
            new ProductUnitResource(
                $productUnit->load('unit')
            ),
            'Product unit retrieved successfully.'
        );
    }

    public function update(
        UpdateProductUnitRequest $request,
        ProductUnit $productUnit
    )
    {
        $productUnit = $this->service->update(
            $productUnit,
            $request->validated()
        );

        return $this->success(
            new ProductUnitResource(
                $productUnit->load('unit')
            ),
            'Product unit updated successfully.'
        );
    }

    public function destroy(
        ProductUnit $productUnit
    )
    {
        $this->service->delete(
            $productUnit
        );

        return $this->success(
            null,
            'Product unit deleted successfully.'
        );
    }
}