<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;

class TaxApplicabilityRuleRules
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
                'Tax applicability rule effective end date cannot be before the start date.'
            );
        }
    }

    public function validatePriority(
        int $priority
    ): void {
        if ($priority < 1) {
            throw new BusinessException(
                'Tax applicability rule priority must be at least 1.'
            );
        }
    }

    public function validateLocationCodes(
        ?string $sourceCountryCode,
        ?string $sourceStateCode,
        ?string $destinationCountryCode,
        ?string $destinationStateCode,
        ?string $placeOfSupplyStateCode
    ): void {
        $codes = [
            'source country' => $sourceCountryCode,
            'source state' => $sourceStateCode,
            'destination country' => $destinationCountryCode,
            'destination state' => $destinationStateCode,
            'place of supply state' => $placeOfSupplyStateCode,
        ];

        foreach ($codes as $label => $code) {
            if (
                $code !== null
                && trim($code) === ''
            ) {
                throw new BusinessException(
                    ucfirst($label) . ' code cannot be empty.'
                );
            }
        }
    }

    public function validateRegistrationRequirement(
        ?bool $sourceRegistrationRequired,
        ?bool $destinationRegistrationRequired
    ): void {
        if (
            $sourceRegistrationRequired === null
            && $destinationRegistrationRequired === null
        ) {
            return;
        }

        if (
            $sourceRegistrationRequired === true
            && $destinationRegistrationRequired === true
        ) {
            return;
        }
    }
}
