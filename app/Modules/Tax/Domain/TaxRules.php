<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxTypeEnum;

class TaxRules
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
                'Tax effective end date cannot be before the start date.'
            );
        }
    }

    public function validateTaxType(
        string $taxType
    ): void {
        $validTypes = array_map(
            static fn(
                TaxTypeEnum $type
            ) => $type->value,
            TaxTypeEnum::cases()
        );

        if (! in_array(
            $taxType,
            $validTypes,
            true
        )) {
            throw new BusinessException(
                'Invalid tax type.'
            );
        }
    }

    public function validateActivePeriod(
        bool $isActive,
        string $effectiveFrom,
        ?string $effectiveTo,
        string $transactionDate
    ): void {
        if (! $isActive) {
            throw new BusinessException(
                'Tax is inactive.'
            );
        }

        if ($transactionDate < $effectiveFrom) {
            throw new BusinessException(
                'Tax is not effective on the transaction date.'
            );
        }

        if (
            $effectiveTo !== null
            && $transactionDate > $effectiveTo
        ) {
            throw new BusinessException(
                'Tax is no longer effective on the transaction date.'
            );
        }
    }
}
