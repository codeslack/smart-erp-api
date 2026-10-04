<?php

namespace App\Modules\Tax\Domain;

use App\Modules\Tax\Enums\TaxRoundingModeEnum;

final class TaxRoundingCalculator
{
    public function round(
        float $amount,
        int $decimalPlaces,
    ): float {
        return round(
            $amount,
            $decimalPlaces,
            PHP_ROUND_HALF_UP
        );
    }

    /**
     * Round component tax amounts independently.
     *
     * Used by PER_COMPONENT rounding.
     */
    public function roundComponents(
        array $components,
        int $decimalPlaces,
    ): array {
        return array_map(
            function (TaxComponentCalculationResult $component) use ($decimalPlaces) {
                return new TaxComponentCalculationResult(
                    taxComponentId: $component->taxComponentId,
                    componentType: $component->componentType,
                    rate: $component->rate,
                    taxableAmount: $this->round(
                        $component->taxableAmount,
                        $decimalPlaces
                    ),
                    taxAmount: $this->round(
                        $component->taxAmount,
                        $decimalPlaces
                    ),
                    calculationMethod: $component->calculationMethod,
                );
            },
            $components
        );
    }

    /**
     * Apply the rounding rule that belongs to this calculation level.
     *
     * PER_COMPONENT:
     *   Each component is rounded independently.
     *
     * PER_LINE:
     *   Components remain unrounded here. The line total is rounded
     *   after all component taxes have been summed.
     *
     * PER_DOCUMENT:
     *   No rounding is performed at this level. Document-level
     *   aggregation must handle the final rounding.
     */
    public function roundComponentsForMode(
        array $components,
        TaxRoundingModeEnum $roundingMode,
        int $decimalPlaces,
    ): array {
        return match ($roundingMode) {
            TaxRoundingModeEnum::PER_COMPONENT => $this->roundComponents(
                $components,
                $decimalPlaces
            ),

            TaxRoundingModeEnum::PER_LINE,
            TaxRoundingModeEnum::PER_DOCUMENT => $components,
        };
    }

    /**
     * Round a calculated tax total.
     *
     * PER_COMPONENT:
     *   Components have already been rounded before summing.
     *
     * PER_LINE:
     *   The complete line tax is rounded here.
     *
     * PER_DOCUMENT:
     *   Document-level rounding is deferred to the document
     *   aggregation layer.
     */
    public function roundTotalTax(
        float $totalTax,
        TaxRoundingModeEnum $roundingMode,
        int $decimalPlaces,
    ): float {
        return match ($roundingMode) {
            TaxRoundingModeEnum::PER_COMPONENT,
            TaxRoundingModeEnum::PER_LINE => $this->round(
                $totalTax,
                $decimalPlaces
            ),

            TaxRoundingModeEnum::PER_DOCUMENT => $totalTax,
        };
    }
}