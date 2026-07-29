<?php

namespace App\Modules\Brand\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Brand\Models\Brand;

use App\Modules\Brand\Services\BrandService;

use App\Modules\Brand\Resources\BrandResource;

use App\Modules\Brand\Requests\StoreBrandRequest;
use App\Modules\Brand\Requests\UpdateBrandRequest;

class BrandController extends ApiController
{
    public function __construct(
        protected BrandService $service
    ) {}

    public function index()
    {
        $brands = $this->service
            ->paginate();

        return $this->success(
            BrandResource::collection(
                $brands
            ),
            'Brands retrieved successfully.'
        );
    }

    public function store(
        StoreBrandRequest $request
    )
    {
        $brand = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new BrandResource(
                $brand
            ),
            'Brand created successfully.',
            201
        );
    }

    public function show(
        Brand $brand
    )
    {
        return $this->success(
            new BrandResource(
                $brand
            ),
            'Brand retrieved successfully.'
        );
    }

    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    )
    {
        $brand = $this->service->update(
            $brand,
            $request->validated()
        );

        return $this->success(
            new BrandResource(
                $brand
            ),
            'Brand updated successfully.'
        );
    }

    public function destroy(
        Brand $brand
    )
    {
        $this->service->delete(
            $brand
        );

        return $this->success(
            null,
            'Brand deleted successfully.'
        );
    }
}