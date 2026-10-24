<?php

namespace App\Modules\ProductUnit\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Rules\ProductUnitRules;

use App\Modules\ProductUnit\Repositories\Contracts\ProductUnitRepositoryInterface;

class ProductUnitService extends BaseService
{
    public function __construct(
        protected ProductUnitRepositoryInterface $productUnits,
        protected ProductRepositoryInterface $products,
        protected ProductUnitRules $rules,
    ) {}

    public function getByProductId(
        int $productId
    ) {
        return $this->productUnits
            ->getByProductId($productId);
    }

    public function findByProductAndUnit(
        int $productId,
        int $unitId
    ): ?ProductUnit {
        return $this->productUnits
            ->findByProductAndUnit(
                $productId,
                $unitId
            );
    }
    
    public function ensureBaseUnitChangeAllowed(
        Product $product,
        int $newUnitId
    ): void {

        if (
            (int) $product->unit_id === $newUnitId
        ) {
            return;
        }

        $existingUnits =
            $this->productUnits
                ->getByProductId(
                    $product->id
                );

        if ($existingUnits->isNotEmpty()) {

            throw new BusinessException(
                'Product base unit cannot be changed because product units are already configured.'
            );
        }
    }

    public function syncBaseUnit(
        Product $product
    ): ProductUnit {

        if (! $product->unit_id) {

            throw new BusinessException(
                'Product base unit is required.'
            );
        }

        $baseUnit =
            $this->productUnits
                ->findByProductAndUnit(
                    $product->id,
                    (int) $product->unit_id
                );

        if ($baseUnit) {

            $this->rules
                ->ensureBaseUnitConversion(
                    $product,
                    $baseUnit
                );

            return $baseUnit;
        }

        return $this->productUnits->create([
            'product_id' => $product->id,
            'unit_id' => (int) $product->unit_id,

            'conversion_factor' => 1,

            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,

            'created_by' => userId(),
        ]);
    }

    public function create(
        array $data
    ): ProductUnit {

        return DB::transaction(
            function () use ($data) {

                $product = $this->products
                    ->findById(
                        (int) $data['product_id']
                    );

                if (! $product instanceof Product) {
                    throw new BusinessException(
                        'Product not found.'
                    );
                }

                $unitId = (int) $data['unit_id'];

                $conversionFactor = (float)
                    $data['conversion_factor'];

                $this->rules
                    ->ensureValidConversionFactor(
                        $conversionFactor
                    );

                $existing = $this->productUnits
                    ->findByProductAndUnit(
                        $product->id,
                        $unitId
                    );

                if ($existing) {
                    throw new BusinessException(
                        'This unit is already configured for the product.'
                    );
                }

                if (
                    (int) $product->unit_id === $unitId
                ) {
                    if (
                        bccomp(
                            (string) $conversionFactor,
                            '1',
                            8
                        ) !== 0
                    ) {
                        throw new BusinessException(
                            'Product base unit must have a conversion factor of 1.'
                        );
                    }
                }

                return $this->productUnits->create([
                    'product_id' => $product->id,
                    'unit_id' => $unitId,
                    'conversion_factor' => $conversionFactor,

                    'is_purchase_unit' => (bool) (
                        $data['is_purchase_unit'] ?? false
                    ),

                    'is_sales_unit' => (bool) (
                        $data['is_sales_unit'] ?? false
                    ),

                    'is_active' => (bool) (
                        $data['is_active'] ?? true
                    ),

                    'created_by' => userId(),
                ]);
            }
        );
    }

    public function update(
        ProductUnit $productUnit,
        array $data
    ): ProductUnit {

        $productUnit->loadMissing(
            'product'
        );

        $product = $productUnit->product;

        if (! $product instanceof Product) {
            throw new BusinessException(
                'Product not found.'
            );
        }

        $conversionFactor = (float) (
            $data['conversion_factor']
            ?? $productUnit->conversion_factor
        );

        $this->rules
            ->ensureValidConversionFactor(
                $conversionFactor
            );

        $this->rules
            ->ensureBelongsToProduct(
                $product,
                $productUnit
            );

        if (
            (int) $product->unit_id ===
            (int) $productUnit->unit_id
        ) {
            if (
                bccomp(
                    (string) $conversionFactor,
                    '1',
                    8
                ) !== 0
            ) {
                throw new BusinessException(
                    'Product base unit must have a conversion factor of 1.'
                );
            }
        }

        return $this->productUnits->update(
            $productUnit,
            [
                'conversion_factor' => $conversionFactor,

                'is_purchase_unit' => $data[
                    'is_purchase_unit'
                ] ?? $productUnit->is_purchase_unit,

                'is_sales_unit' => $data[
                    'is_sales_unit'
                ] ?? $productUnit->is_sales_unit,

                'is_active' => $data[
                    'is_active'
                ] ?? $productUnit->is_active,

                'updated_by' => userId(),
            ]
        );
    }

    public function delete(
        ProductUnit $productUnit
    ): bool {
        return $this->productUnits->delete(
            $productUnit
        );
    }

    public function toBaseQuantity(
        ProductUnit $productUnit,
        float $quantity
    ): float {

        $this->rules
            ->ensureActive($productUnit);

        return $this->rules
            ->calculateBaseQuantity(
                quantity: $quantity,
                conversionFactor: (float)
                    $productUnit->conversion_factor
            );
    }

    /**
     * Convert base quantity into the selected product unit.
     */
    public function fromBaseQuantity(
        ProductUnit $productUnit,
        float $baseQuantity
    ): float {

        $this->rules
            ->ensureActive($productUnit);

        return $this->rules
            ->calculateFromBaseQuantity(
                baseQuantity: $baseQuantity,
                conversionFactor: (float)
                    $productUnit->conversion_factor
            );
    }

    public function toBaseUnitCost(    
        ProductUnit $productUnit,
        float $unitCost
    ): float {

        $this->rules
            ->ensureActive($productUnit);

        return $this->rules
            ->calculateBaseUnitCost(
                unitCost: $unitCost,
                conversionFactor: (float)
                    $productUnit->conversion_factor
            );
    }

    public function ensurePurchaseUnit(
        ProductUnit $productUnit
    ): ProductUnit {

        $this->rules
            ->ensureActive($productUnit);

        $this->rules
            ->ensurePurchaseUnit($productUnit);

        return $productUnit;
    }

    public function ensureSalesUnit(
        ProductUnit $productUnit
    ): ProductUnit {

        $this->rules
            ->ensureActive($productUnit);

        $this->rules
            ->ensureSalesUnit($productUnit);

        return $productUnit;
    }

    public function ensureBaseUnit(
        Product $product,
        ProductUnit $productUnit
    ): ProductUnit {

        $this->rules
            ->ensureProductBaseUnit(
                $product,
                $productUnit
            );

        return $productUnit;
    }
}