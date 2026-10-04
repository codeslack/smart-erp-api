<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;

class TaxGroupRules
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
                'Tax group effective end date cannot be before the start date.'
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
                'Tax group is inactive.'
            );
        }

        if ($transactionDate < $effectiveFrom) {
            throw new BusinessException(
                'Tax group is not effective on the transaction date.'
            );
        }

        if (
            $effectiveTo !== null
            && $transactionDate > $effectiveTo
        ) {
            throw new BusinessException(
                'Tax group is no longer effective on the transaction date.'
            );
        }
    }

    public function validateComponentsCount(
        int $componentCount
    ): void {
        if ($componentCount < 1) {
            throw new BusinessException(
                'Tax group must contain at least one tax component.'
            );
        }
    }

    public function validateUsage(
        string $usage
    ): void {
        $this->validateEnumValue(
            $usage,
            TaxUsageEnum::cases(),
            'tax group usage'
        );
    }

    public function validateDirection(
        string $direction
    ): void {
        $this->validateEnumValue(
            $direction,
            TaxDirectionEnum::cases(),
            'tax group direction'
        );
    }

    public function validateTreatment(
        string $treatment
    ): void {
        $this->validateEnumValue(
            $treatment,
            TaxTreatmentEnum::cases(),
            'tax group treatment'
        );
    }

    protected function validateEnumValue(
        string $value,
        array $cases,
        string $field
    ): void {
        foreach ($cases as $case) {
            if ($case->value === $value) {
                return;
            }
        }

        throw new BusinessException(
            "Invalid {$field}."
        );
    }
}