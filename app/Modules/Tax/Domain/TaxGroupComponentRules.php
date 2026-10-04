<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

class TaxGroupComponentRules
{
    public function validateRate(
        float $rate,
        string $calculationMethod
    ): void {
        if ($rate < 0) {
            throw new BusinessException(
                'Tax rate cannot be negative.'
            );
        }

        if (
            $calculationMethod === TaxCalculationMethodEnum::PERCENTAGE->value
            && $rate > 100
        ) {
            throw new BusinessException(
                'Percentage tax rate cannot exceed 100.'
            );
        }
    }

    public function validateCalculationMethod(
        string $calculationMethod
    ): void {
        $validMethods = array_map(
            static fn(
                TaxCalculationMethodEnum $method
            ) => $method->value,
            TaxCalculationMethodEnum::cases()
        );

        if (! in_array(
            $calculationMethod,
            $validMethods,
            true
        )) {
            throw new BusinessException(
                'Invalid tax calculation method.'
            );
        }
    }

    public function validateEffectivePeriod(
        string $effectiveFrom,
        ?string $effectiveTo
    ): void {
        if (
            $effectiveTo !== null
            && $effectiveTo < $effectiveFrom
        ) {
            throw new BusinessException(
                'Tax effective end date cannot be before the start date.'
            );
        }
    }
}
