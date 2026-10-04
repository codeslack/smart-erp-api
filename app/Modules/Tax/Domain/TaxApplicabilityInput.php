<?php

namespace App\Modules\Tax\Domain;

use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Enums\TaxDirectionEnum;

final class TaxApplicabilityInput
{
    public function __construct(
        public readonly TaxUsageEnum $usage,
        public readonly TaxDirectionEnum $direction,
        public readonly ?string $sourceCountryCode,
        public readonly ?string $sourceStateCode,
        public readonly ?string $destinationCountryCode,
        public readonly ?string $destinationStateCode,
        public readonly ?string $placeOfSupplyStateCode,
        public readonly ?bool $sourceRegistrationRequired,
        public readonly ?bool $destinationRegistrationRequired,
        public readonly string $calculationDate,
    ) {
    }
}