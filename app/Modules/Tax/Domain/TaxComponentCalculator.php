<?php

namespace App\Modules\Tax\Domain;

use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

final class TaxComponentCalculator
{
    public function calculate(
        int $taxComponentId,
        string $componentType,
        float $rate,
        float $taxableAmount,
        TaxCalculationMethodEnum $calculationMethod,
    ): TaxComponentCalculationResult {
        $taxAmount = match ($calculationMethod) {
            TaxCalculationMethodEnum::PERCENTAGE =>
                $taxableAmount * ($rate / 100),

            TaxCalculationMethodEnum::FIXED =>
                $rate,
        };

        return new TaxComponentCalculationResult(
            taxComponentId: $taxComponentId,
            componentType: $componentType,
            rate: $rate,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            calculationMethod: $calculationMethod->value,
        );
    }
}