<?php

namespace App\Modules\Product\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;

class ProductVariantServiceOld extends BaseService
{
    public function __construct(
        protected ProductVariantRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15
    ) {
        return $this->repository
            ->paginateWithRelations(
                $perPage
            );
    }

    public function search(
        ?string $search,
        int $perPage = 15
    ) {
        return $this->repository
            ->search(
                $search,
                $perPage
            );
    }

    public function create(
        Product $product,
        array $data
    ): ProductVariant {

        return DB::transaction(
            function () use (
                $product,
                $data
            ) {

                $attributes =
                    $data['attributes'] ?? [];

                unset(
                    $data['attributes']
                );

                $data['product_id'] =
                    $product->id;

                $this->validateProduct(
                    $product
                );

                $data['code'] =
                    nextSystemNumber(
                        SystemNumberTypeEnum::PRODUCT_VARIANT
                    );

                $variant =
                    $this->repository->create(
                        $data
                    );

                $this->createAttributes(
                    $variant,
                    $attributes
                );

                return $variant->load(
                    'product',
                    'attributes'
                );
            }
        );
    }

    public function update(
        ProductVariant $variant,
        array $data
    ): ProductVariant {

        return DB::transaction(
            function () use (
                $variant,
                $data
            ) {

                $attributes = $data['attributes'] ?? [];

                unset(
                    $data['attributes']
                );

                $product = Product::findOrFail(
                    $data['product_id'] ?? $variant->product_id
                );

                $this->validateProduct(
                    $product
                );

                $variant = $this->repository
                    ->update(
                        $variant,
                        $data
                    );

                $this->replaceAttributes(
                    $variant,
                    $attributes
                );

                return $variant->load(
                    'product',
                    'attributes'
                );
            }
        );
    }

    public function delete(
        ProductVariant $variant
    ): bool {

        return DB::transaction(
            function () use (
                $variant
            ) {

                $variant->attributes()
                    ->delete();

                return $this->repository
                    ->delete(
                        $variant
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    private function createAttributes(
        ProductVariant $variant,
        array $attributes
    ): void {

        foreach ($attributes as $attribute) {

            $variant->attributes()->create([

                'attribute_name' =>
                $attribute['attribute_name'],

                'attribute_value' =>
                $attribute['attribute_value'],
            ]);
        }
    }

    private function replaceAttributes(
        ProductVariant $variant,
        array $attributes
    ): void {

        $variant->attributes()
            ->delete();

        $this->createAttributes(
            $variant,
            $attributes
        );
    }

    private function validateProduct(
        Product $product
    ): void {

        if ($product->isService()) {

            throw new BusinessException(
                'Service products cannot have variants.'
            );
        }

        if (! $product->supportsVariants()) {

            throw new BusinessException(
                'Variants are not enabled for this product.'
            );
        }
    }
}
