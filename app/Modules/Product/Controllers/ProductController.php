<?php

namespace App\Modules\Product\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

use App\Modules\Product\Models\Product;

use App\Modules\Product\Services\ProductService;

use App\Modules\Product\Resources\ProductResource;

use App\Modules\Product\Requests\StoreProductRequest;
use App\Modules\Product\Requests\UpdateProductRequest;

class ProductController extends ApiController
{
    public function __construct(
        protected ProductService $service
    ) {}

    /**
     * List Products
     *
     * Retrieves a paginated list of products.
     */
    public function index(
        Request $request
    ) {

        $products = filled(
            $request->search
        )

            ? $this->service->search(
                $request->string(
                    'search'
                ),
                $request->integer(
                    'per_page',
                    15
                )
            )

            : $this->service->paginate(
                $request->integer(
                    'per_page',
                    15
                )
            );

        // Eager load your nested relationships right here
        // $products->load(['category', 'brand', 'unit', 'variants']);

        return $this->success(
            ProductResource::collection(
                $products
            ),
            'Products retrieved successfully.'
        );
    }

    /**
     * Create Product
     *
     * Creates a new product.
     */
    public function store(
        StoreProductRequest $request
    ) {

        $product = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new ProductResource(
                $product
            ),
            'Product created successfully.',
            201
        );
    }

    /**
     * Show Product
     *
     * Display a specific product.
     */
    public function show(
        Product $product
    ) {
        
        $product->load([
            'category',
            'brand',
            'unit',
            'variants.attributes',
        ]);

        return $this->success(
            new ProductResource(
                $product
            ),
            'Product retrieved successfully.'
        );
    }

    /**
     * UpdateProduct
     *
     * Update an existing product.
     */
    public function update(
        UpdateProductRequest $request,
        Product $product
    ) {
        $product = $this->service->update(
            $product,
            $request->validated()
        );

        return $this->success(
            new ProductResource(
                $product
            ),
            'Product updated successfully.'
        );
    }

    /**
     * Delete Product
     *
     * Deletes a specific product.
     */
    public function destroy(
        Product $product
    ) {
        $this->service->delete(
            $product
        );

        return $this->success(
            null,
            'Product deleted successfully.'
        );
    }
}
