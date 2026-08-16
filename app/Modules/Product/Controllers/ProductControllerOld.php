<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Product\Models\Product;
use App\Core\Exceptions\BusinessException;
use App\Modules\Product\Services\ProductService;
use App\Modules\Product\Resources\ProductResource;
use App\Modules\Product\Requests\StoreProductRequest;
use App\Modules\Product\Requests\UpdateProductRequest;
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

use Illuminate\Http\JsonResponse;

class ProductControllerOld extends ApiController
{
    public function __construct(
        protected ProductRepositoryInterface $products,
        protected ProductService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        $products = $this->products
            ->paginate();

        return $this->success(
            ProductResource::collection(
                $products
            )
        );
    }

    public function search(): JsonResponse
    {
        $products = $this->products->search(
            request('search')
        );

        return $this->success(
            ProductResource::collection(
                $products
            )
        );
    }    

    public function store(
        StoreProductRequest $request
    ): JsonResponse {

        $product = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new ProductResource( $product ),
            'Product created successfully.',
            201
        );
    }

    public function show(
        Product $product
    ): JsonResponse {

        return $this->success(
            new ProductResource(
                $product->load([
                    'category',
                    'brand',
                    'unit',
                ])
            )
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): JsonResponse {

        $product = $this->service->update(
            $product,
            $request->validated()
        );

        return $this->success(
            new ProductResource($product),
            'Product updated successfully.'
        );
    }

    public function destroy(
        Product $product
    ): JsonResponse {

        if ($product->stocks()->exists()) {
            throw new BusinessException(
                'Cannot delete product with inventory history.'
            );
        }

        if ($product->stockLedgers()->exists()) {
            throw new BusinessException(
                'Cannot delete product with inventory history.'
            );
        }

        $this->products->delete(
            $product
        );

        return $this->success(
            null,
            'Product deleted successfully.'
        );
    }
}