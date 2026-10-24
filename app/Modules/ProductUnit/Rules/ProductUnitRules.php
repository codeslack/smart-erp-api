<?php

namespace App\Modules\ProductUnit\Rules;

use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;

class ProductUnitRules
{
    /**
     * Validate the conversion factor.
     *
     * The factor means:
     *
     * 1 selected unit = X base units.
     */
    public function ensureValidConversionFactor(
        float $conversionFactor
    ): void {
        if ($conversionFactor <= 0) {
            throw new BusinessException(
                'Conversion factor must be greater than zero.'
            );
        }
    }

    /**
     * Ensure the product base unit uses factor 1.
     */
    public function ensureBaseUnitConversion(
        Product $product,
        ProductUnit $productUnit
    ): void {
        if ((int) $product->unit_id !== (int) $productUnit->unit_id) {
            return;
        }

        if (
            bccomp(
                (string) $productUnit->conversion_factor,
                '1',
                8
            ) !== 0
        ) {
            throw new BusinessException(
                'Product base unit must have a conversion factor of 1.'
            );
        }
    }

    /**
     * Ensure a product unit belongs to the product's
     * configured base-unit hierarchy.
     */
    public function ensureBelongsToProduct(
        Product $product,
        ProductUnit $productUnit
    ): void {
        if (
            (int) $productUnit->product_id !==
            (int) $product->id
        ) {
            throw new BusinessException(
                'Product unit does not belong to the selected product.'
            );
        }
    }

    /**
     * Ensure the base unit is configured correctly.
     */
    public function ensureProductBaseUnit(
        Product $product,
        ProductUnit $productUnit
    ): void {
        if (
            (int) $product->unit_id !==
            (int) $productUnit->unit_id
        ) {
            throw new BusinessException(
                'The selected unit is not the product base unit.'
            );
        }

        if (
            bccomp(
                (string) $productUnit->conversion_factor,
                '1',
                8
            ) !== 0
        ) {
            throw new BusinessException(
                'Product base unit must have a conversion factor of 1.'
            );
        }
    }

    /**
     * Ensure an active product unit can be used.
     */
    public function ensureActive(
        ProductUnit $productUnit
    ): void {
        if (! $productUnit->is_active) {
            throw new BusinessException(
                'The selected product unit is inactive.'
            );
        }
    }

    /**
     * Ensure the unit is allowed for purchasing.
     */
    public function ensurePurchaseUnit(
        ProductUnit $productUnit
    ): void {
        if (! $productUnit->is_purchase_unit) {
            throw new BusinessException(
                'The selected unit is not enabled for purchasing.'
            );
        }
    }

    /**
     * Ensure the unit is allowed for sales.
     */
    public function ensureSalesUnit(
        ProductUnit $productUnit
    ): void {
        if (! $productUnit->is_sales_unit) {
            throw new BusinessException(
                'The selected unit is not enabled for sales.'
            );
        }
    }

    /**
     * Convert document quantity into base quantity.
     */
    public function calculateBaseQuantity(
        float $quantity,
        float $conversionFactor
    ): float {
        $this->ensurePositiveQuantity($quantity);
        $this->ensureValidConversionFactor($conversionFactor);

        return round(
            $quantity * $conversionFactor,
            8
        );
    }

    /**
     * Convert base quantity into document-unit quantity.
     *
     * Example:
     * 1 BOX = 10 PCS
     * 30 PCS = 3 BOX
     */
    public function calculateFromBaseQuantity(
        float $baseQuantity,
        float $conversionFactor
    ): float {
        $this->ensurePositiveQuantity($baseQuantity);
        $this->ensureValidConversionFactor($conversionFactor);

        return round(
            $baseQuantity / $conversionFactor,
            8
        );
    }

    /**
     * Convert document-unit cost into base-unit cost.
     */    
    public function calculateBaseUnitCost(
        float $unitCost,
        float $conversionFactor
    ): float {
        if ($unitCost < 0) {
            throw new BusinessException(
                'Unit cost cannot be negative.'
            );
        }

        $this->ensureValidConversionFactor($conversionFactor);

        return round(
            $unitCost / $conversionFactor,
            8
        );
    }

    /**
     * Validate document quantity.
     */
    public function ensurePositiveQuantity(
        float $quantity
    ): void {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Quantity must be greater than zero.'
            );
        }
    }
}