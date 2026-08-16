<?php

namespace App\Modules\Product\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Core\Services\BaseService;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Support\ProductDefaults;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductServiceOld extends BaseService
{
    public function __construct(
        protected ProductRepositoryInterface $products
    ) {}

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {

            $data['code'] = nextDocumentNumber(
                'product',
                'PRD'
            );

            $defaults = ProductDefaults::for(
                $data['product_type']
            );

            $data = array_merge(
                $defaults,
                $data
            );

            $data = $this->prepareData($data);

            return $this->products->create($data);
        });
    }

    public function update(
        Product $product,
        array $data
    ): Product {

        return DB::transaction(function () use (
            $product,
            $data
        ) {

            if (isset($data['product_type'])) {

                $defaults = ProductDefaults::for(
                    $data['product_type']
                );

                $data = array_merge(
                    $defaults,
                    $data
                );
            }

            $data = $this->prepareData(
                $data,
                $product
            );

            $this->products->update(
                $product,
                $data
            );

            return $product->refresh();
        });
    }

    protected function prepareData(
        array $data,
        ?Product $product = null
    ): array {

        $data['sku'] ??= $product?->sku
            ?? $this->generateSku();

        if (isset($data['name'])) {
            $data['slug'] = Str::slug(
                $data['name']
            );
        }

        $this->validateTrackingRules($data);

        return $data;
    }

    protected function generateSku(): string
    {
        do {

            $sku = 'PRD-' . strtoupper(
                Str::random(8)
            );
        } while (
            $this->products->existsBySku($sku)
        );

        return $sku;
    }

    protected function validateTrackingRules(
        array $data
    ): void {

        if (
            ($data['track_batch'] ?? false)
            &&
            ($data['track_serial'] ?? false)
        ) {
            throw new BusinessException(
                'Product cannot use batch and serial tracking together.'
            );
        }

        if (
            ($data['has_expiry'] ?? false)
            &&
            ! ($data['track_batch'] ?? false)
        ) {
            throw new BusinessException(
                'Expiry tracking requires batch tracking.'
            );
        }
    }
}
