<?php

namespace App\Modules\Tax\Domain;

use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;

final class TaxCalculationInput
{
    public function __construct(
        public readonly int $taxGroupId,
        public readonly float $taxableAmount,
        public readonly TaxPricingModeEnum $pricingMode,
        public readonly TaxRoundingModeEnum $roundingMode,
        public readonly string $calculationDate,
    ) {
    }
}