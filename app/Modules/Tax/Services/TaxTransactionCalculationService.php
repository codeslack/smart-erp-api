<?php

namespace App\Modules\Tax\Services;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Domain\TaxTransactionCalculationResult;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;

class TaxTransactionCalculationService
{
    public function __construct(
        protected TaxApplicabilityService $applicability,
        protected TaxCalculationService $calculation,
    ) {
    }

    public function calculate(
        TaxApplicabilityInput $applicabilityInput,
        float $taxableAmount,
        TaxPricingModeEnum $pricingMode,
        TaxRoundingModeEnum $roundingMode,
        int $decimalPlaces = 2,
    ): TaxTransactionCalculationResult {
        $applicabilityResult = $this->applicability->resolve(
            $applicabilityInput,
        );

        $calculationResult = $this->calculation->calculate(
            new TaxCalculationInput(
                taxGroupId: $applicabilityResult->taxGroupId,
                taxableAmount: $taxableAmount,
                pricingMode: $pricingMode,
                roundingMode: $roundingMode,
                calculationDate: $applicabilityInput->calculationDate,
            ),
            decimalPlaces: $decimalPlaces,
        );

        return new TaxTransactionCalculationResult(
            applicability: $applicabilityResult,
            calculation: $calculationResult,
        );
    }
}