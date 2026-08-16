<?php

namespace App\Modules\Product\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\Product\Services\ProductVariantService;

use App\Modules\Product\Resources\ProductVariantResource;

use App\Modules\Product\Requests\StoreProductVariantRequest;
use App\Modules\Product\Requests\UpdateProductVariantRequest;

class ProductVariantController extends ApiController
{
    public function __construct(
        protected ProductVariantService $service
    ) {}

    /**
     * List Product Variants
     *
     * Retrieves a paginated list of product variants.
     */
    public function index(
        Product $product,
        Request $request
    )
    {
        // $variants = filled($request->search)

        //     ? $this->service->search(
        //         $product,
        //         $request->string('search'),
        //         $request->integer('per_page', 15)
        //     )

        //     : $this->service->paginate(
        //         $product,
        //         $request->integer('per_page', 15)
        //     );

        return $this->success(
            ProductVariantResource::collection($product->variants),
            'Product variants retrieved successfully.'
        );
    }

    /**
     * Create Product Variant
     *
     * Creates a new product variant.
     */
    public function store(
        Product $product,
        StoreProductVariantRequest $request
    )
    {
        $variant = $this->service->create(
            $product,
            $request->validated()
        );

        return $this->success(
            new ProductVariantResource(
                $variant
            ),
            'Product variant created successfully.',
            201
        );
    }

    /**
     * Show Product Variant
     *
     * Display a specific product variant.
     */
    public function show(
        ProductVariant $productVariant
    ) {
        $productVariant->load(
            'product',
            'attributes'
        );

        return $this->success(
            new ProductVariantResource(
                $productVariant
            ),
            'Product variant retrieved successfully.'
        );
    }

    /**
     * Update Product Variant
     *
     * Updates a specific product variant.
     */
    public function update(
        UpdateProductVariantRequest $request,
        ProductVariant $productVariant
    ) {
        $productVariant = $this->service
            ->update(
                $productVariant,
                $request->validated()
            );

        return $this->success(
            new ProductVariantResource(
                $productVariant
            ),
            'Product variant updated successfully.'
        );
    }

    /**
     * Delete Product Variant
     *
     * Deletes a specific product variant.
     */
    public function destroy(
        ProductVariant $productVariant
    ) {
        $this->service
            ->delete(
                $productVariant
            );

        return $this->success(
            null,
            'Product variant deleted successfully.'
        );
    }
}
