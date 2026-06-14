<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductService;
use App\Modules\Product\Resources\ProductResource;
use App\Modules\Product\Requests\StoreProductRequest;
use App\Modules\Product\Requests\UpdateProductRequest;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service
    ) {}

    public function index()
    {
        return ProductResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreProductRequest $request
    ) {
        return new ProductResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(Product $product)
    {
        return new ProductResource(
            $this->service->find($product->id)
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ) {
        return new ProductResource(
            $this->service->update(
                $product->id,
                $request->validated()
            )
        );
    }

    public function destroy(Product $product)
    {
        $this->service->delete(
            $product->id
        );

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}