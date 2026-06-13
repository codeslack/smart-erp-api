<?php

namespace App\Modules\Brand\Controllers;

use App\Modules\Brand\Models\Brand;
use App\Http\Controllers\Controller;
use App\Modules\Brand\Resources\BrandResource;
use App\Modules\Brand\Requests\StoreBrandRequest;
use App\Modules\Brand\Requests\UpdateBrandRequest;

class BrandController extends Controller
{
    public function index()
    {
        return BrandResource::collection(
            Brand::latest()->paginate()
        );
    }

    public function store(StoreBrandRequest $request)
    {
        $brand = Brand::create(
            $request->validated()
        );

        return new BrandResource($brand);
    }

    public function show(Brand $brand)
    {
        return new BrandResource($brand);
    }

    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    ) {
        $brand->update($request->validated());

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully',
        ]);
    }
}
