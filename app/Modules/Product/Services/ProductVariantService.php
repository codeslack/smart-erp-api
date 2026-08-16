<?php

namespace App\Modules\Product\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;

class ProductVariantService extends BaseService
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
    | Sync Variants
    |--------------------------------------------------------------------------
    */

    public function sync(
        Product $product,
        array $variants
    ): void {

        $this->validateProduct(
            $product
        );

        $existingVariantIds = [];

        foreach ($variants as $variantData) {

            $attributes =
                $variantData['attributes'] ?? [];

            unset(
                $variantData['attributes']
            );

            /*
            |--------------------------------------------------------------------------
            | Update Existing Variant
            |--------------------------------------------------------------------------
            */

            if (! empty($variantData['uuid'])) {

                $variant = ProductVariant::query()
                    ->where(
                        'product_id',
                        $product->id
                    )
                    ->where(
                        'uuid',
                        $variantData['uuid']
                    )
                    ->first();

                if ($variant) {

                    $variant->update([

                        'sku'            => $variantData['sku'] ?? $variant->sku,

                        'barcode'        => $variantData['barcode'] ?? $variant->barcode,

                        'name'           => $variantData['name'],

                        'purchase_price' => $variantData['purchase_price'] ?? 0,

                        'selling_price'  => $variantData['selling_price'] ?? 0,

                        'is_active'      => $variantData['is_active'] ?? true,
                    ]);

                    $this->syncAttributes(
                        $variant,
                        $attributes
                    );

                    $existingVariantIds[] =
                        $variant->id;

                    continue;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create New Variant
            |--------------------------------------------------------------------------
            */

            $variant = ProductVariant::create([

                'product_id' => $product->id,

                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::PRODUCT_VARIANT
                ),

                'sku' => $variantData['sku'],

                'barcode' => $variantData['barcode'] ?? null,

                'name' => $variantData['name'],

                'purchase_price' => $variantData['purchase_price'] ?? 0,

                'selling_price' => $variantData['selling_price'] ?? 0,

                'is_active' => $variantData['is_active'] ?? true,
            ]);

            $this->syncAttributes(
                $variant,
                $attributes
            );

            $existingVariantIds[] =
                $variant->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Removed Variants
        |--------------------------------------------------------------------------
        */

        ProductVariant::query()
            ->where(
                'product_id',
                $product->id
            )
            ->whereNotIn(
                'id',
                $existingVariantIds
            )
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    private function syncAttributes(
        ProductVariant $variant,
        array $attributes
    ): void {

        $variant->attributes()
            ->delete();

        foreach ($attributes as $attribute) {

            $variant->attributes()->create([

                'attribute_name' =>
                    $attribute['attribute_name'],

                'attribute_value' =>
                    $attribute['attribute_value'],
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

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
}
