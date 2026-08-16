<?php

namespace App\Modules\Product\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductService extends BaseService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected ProductVariantService $productVariantService,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return Product::query()
            ->with(['category', 'brand', 'unit', 'variants.attributes']) // Eager loads all 4 relations
            ->latest() // Optional: keeps recent products on page 1
            ->paginate($perPage);
    }

    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator {

        $query = Product::query()->with(['category', 'brand', 'unit', 'variants.attributes']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(
        array $data
    ): Product {

        return DB::transaction(
            function () use ($data) {

                logger()->info(
                    'ProductService create fired',
                    [
                        'request_data' => $data,
                    ]
                );

                $variants =
                    $data['variants'] ?? [];

                unset(
                    $data['variants']
                );

                $data['code'] =
                    nextSystemNumber(
                        SystemNumberTypeEnum::PRODUCT
                    );

                $data['slug'] =
                    $this->generateUniqueSlug(
                        $data['name']
                    );

                $product =
                    $this->productRepository
                    ->create(
                        $data
                    );

                if (
                    $product->has_variants &&
                    ! empty($variants)
                ) {

                    $this->createVariants(
                        $product,
                        $variants
                    );
                }

                return $product->load([
                    'category',
                    'brand',
                    'unit',
                    'variants.attributes',
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Product $product,
        array $data
    ): Product {

        return DB::transaction(
            function () use (
                $product,
                $data
            ) {

                logger()->info(
                    'ProductService update fired',
                    [
                        'product_id' => $product->id,
                        'request_data' => $data,
                    ]
                );

                $variants =
                    $data['variants'] ?? [];

                unset(
                    $data['variants']
                );

                // validate 
                $this->validateLockedFields(
                    $product,
                    $data
                );

                if (
                    isset($data['name']) &&
                    $data['name'] !== $product->name
                ) {

                    $data['slug'] =
                        $this->generateUniqueSlug(
                            $data['name'],
                            $product->id
                        );
                }

                $data = array_filter(
                    $data,
                    fn ($value) => !is_null($value)
                );

                $product =
                    $this->productRepository
                    ->update(
                        $product,
                        $data
                    );

                if ($product->has_variants &&
                        !empty($variants)
                    ) {

                    $this->syncVariants(
                        $product,
                        $variants
                    );
                } else {

                    $product->variants()
                        ->delete();
                }

                return $product->load([
                    'category',
                    'brand',
                    'unit',
                    'variants.attributes',
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function delete(
        Product $product
    ): bool {

        if ($this->hasTransactions($product)) {

            throw new BusinessException(
                'Product cannot be deleted because transactions exist.'
            );
        }

        return DB::transaction(
            function () use ($product) {

                $product->variants()
                    ->delete();

                return $this->productRepository
                    ->delete(
                        $product
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    */

    private function createVariants(
        Product $product,
        array $variants
    ): void {

        foreach ($variants as $variant) {

            $this->productVariantService
                ->create(
                    $product,
                    $variant
                );
        }
    }

    private function syncVariants(
        Product $product,
        array $variants
    ): void {

        $existingVariantIds =
            $product->variants()
            ->pluck('id')
            ->toArray();

        $submittedVariantIds = [];

        foreach ($variants as $variantData) {

            /*
            |--------------------------------------------------------------------------
            | Existing Variant
            |--------------------------------------------------------------------------
            */

            if (
                ! empty($variantData['uuid'])
            ) {

                $variant =
                    $product->variants()
                    ->where(
                        'uuid',
                        $variantData['uuid']
                    )
                    ->first();

                if ($variant) {

                    $this->productVariantService
                        ->update(
                            $variant,
                            $variantData
                        );

                    $submittedVariantIds[] =
                        $variant->id;

                    continue;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | New Variant
            |--------------------------------------------------------------------------
            */

            $newVariant =
                $this->productVariantService
                ->create(
                    $product,
                    $variantData
                );

            $submittedVariantIds[] =
                $newVariant->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Removed Variants
        |--------------------------------------------------------------------------
        */

        $variantsToDelete =
            array_diff(
                $existingVariantIds,
                $submittedVariantIds
            );

        if (
            ! empty($variantsToDelete)
        ) {

            ProductVariant::query()
                ->whereIn(
                    'id',
                    $variantsToDelete
                )
                ->delete();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {

        $slug = Str::slug(
            $name
        );

        $originalSlug = $slug;

        $counter = 2;

        while (
            $this->productRepository
            ->existsBySlug(
                $slug,
                $ignoreId
            )
        ) {

            $slug =
                $originalSlug .
                '-' .
                $counter;

            $counter++;
        }

        return $slug;
    }

    private function hasTransactions(
        Product $product
    ): bool {

        return $product
            ->stockLedgers()
            ->exists();
    }

    private function validateLockedFields(
        Product $product,
        array $data
    ): void {

        if (! $product->isLocked()) {
            return;
        }

        foreach (
            [
                'sku',
                'barcode',
                'unit_id',
                'product_type',
                'inventory_tracking_type',
            ] as $field
        ) {

            if (
                array_key_exists($field, $data) &&
                $data[$field] != $product->{$field}
            ) {

                throw new BusinessException(
                    "{$field} cannot be modified because transactions exist."
                );
            }
        }
    }
}
