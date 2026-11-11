<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxRegistrationTypeEnum;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;

class TaxRegistrationRules
{
    /**
     * @return array<string, class-string>
     */
    public static function registrableMorphMap(): array
    {
        return [
            'customer' => Customer::class,
            'supplier' => Supplier::class,
        ];
    }

    public function validateRegistrable(
        string $registrableType,
        int $registrableId
    ): void {
        $modelClass = self::registrableMorphMap()[$registrableType] ?? null;

        if ($modelClass === null) {
            throw new BusinessException(
                'Invalid tax registration entity type.'
            );
        }

        if (! $modelClass::query()->whereKey($registrableId)->exists()) {
            throw new BusinessException(
                'Tax registration entity not found.'
            );
        }
    }

    public function validateRegistrationType(
        string $registrationType
    ): void {
        $validTypes = array_map(
            static fn(
                TaxRegistrationTypeEnum $type
            ) => $type->value,
            TaxRegistrationTypeEnum::cases()
        );

        if (! in_array(
            $registrationType,
            $validTypes,
            true
        )) {
            throw new BusinessException(
                'Invalid tax registration type.'
            );
        }
    }

    public function validateRegistrationNumber(
        string $registrationType,
        string $registrationNumber
    ): void {
        if (
            $registrationType === TaxRegistrationTypeEnum::GSTIN->value
            && ! preg_match(
                '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z0-9][A-Z0-9]Z[A-Z0-9]$/',
                strtoupper($registrationNumber)
            )
        ) {
            throw new BusinessException(
                'Invalid GSTIN format.'
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
                'Tax registration effective end date cannot be before the start date.'
            );
        }
    }

    public function validatePrimaryRegistration(
        bool $isPrimary,
        bool $isActive
    ): void {
        if ($isPrimary && ! $isActive) {
            throw new BusinessException(
                'An inactive tax registration cannot be primary.'
            );
        }
    }
}
