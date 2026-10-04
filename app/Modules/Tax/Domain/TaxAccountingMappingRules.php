<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxDirectionEnum;

class TaxAccountingMappingRules
{
    public function validateEffectivePeriod(
        string $effectiveFrom,
        ?string $effectiveTo
    ): void {
        if (
            $effectiveTo !== null
            && $effectiveTo < $effectiveFrom
        ) {
            throw new BusinessException(
                'Tax accounting mapping effective end date cannot be before the start date.'
            );
        }
    }

    public function validateDirection(
        string $direction
    ): void {
        $validDirections = array_map(
            static fn (
                TaxDirectionEnum $type
            ) => $type->value,
            TaxDirectionEnum::cases()
        );

        if (! in_array(
            $direction,
            $validDirections,
            true
        )) {
            throw new BusinessException(
                'Invalid tax accounting mapping direction.'
            );
        }
    }
}