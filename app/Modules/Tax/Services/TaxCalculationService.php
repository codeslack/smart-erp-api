<?php

namespace App\Modules\Tax\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Domain\TaxCalculationResult;
use App\Modules\Tax\Domain\TaxComponentCalculator;
use App\Modules\Tax\Domain\TaxPricingCalculator;
use App\Modules\Tax\Domain\TaxRoundingCalculator;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

use App\Modules\Tax\Repositories\Contracts\TaxGroupRepositoryInterface;

class TaxCalculationService
{
    public function __construct(
        protected TaxGroupRepositoryInterface $groups,
        protected TaxComponentCalculator $componentCalculator,
        protected TaxPricingCalculator $pricingCalculator,
        protected TaxRoundingCalculator $roundingCalculator,
    ) {
    }

    public function calculate(
        TaxCalculationInput $input,
        int $decimalPlaces = 2,
    ): TaxCalculationResult {
        $this->validateAmount($input->taxableAmount);

        $group = $this->groups->findForCalculation(
            $input->taxGroupId,
            $input->calculationDate,
        );

        if ($group === null) {
            throw new BusinessException(
                'The selected tax group is not active or effective on the calculation date.',
            );
        }

        $components = $group->components;

        if ($components->isEmpty()) {
            throw new BusinessException(
                'The selected tax group has no effective tax components.',
            );
        }

        $percentageRate = $components
            ->filter(
                fn ($component) =>
                    $component->calculation_method ===
                    TaxCalculationMethodEnum::PERCENTAGE->value
            )
            ->sum(fn ($component) => (float) $component->rate);

        $hasFixedComponent = $components->contains(
            fn ($component) =>
                $component->calculation_method ===
                TaxCalculationMethodEnum::FIXED->value
        );

        $taxableAmount = $this->pricingCalculator->taxableAmount(
            amount: $input->taxableAmount,
            pricingMode: $input->pricingMode->value,
            percentageRate: $percentageRate,
            hasFixedComponent: $hasFixedComponent,
        );

        $calculatedComponents = [];

        foreach ($components as $component) {
            if ($component->taxComponent === null) {
                throw new BusinessException(
                    'A tax group component references an invalid tax component.',
                );
            }

            $calculatedComponents[] = $this->componentCalculator->calculate(
                taxComponentId: $component->tax_component_id,
                componentType: $component->taxComponent->component_type,
                rate: (float) $component->rate,
                taxableAmount: $taxableAmount,
                calculationMethod: TaxCalculationMethodEnum::from(
                    $component->calculation_method,
                ),
            );
        }

        $calculatedComponents = $this->roundingCalculator
            ->roundComponentsForMode(
                $calculatedComponents,
                $input->roundingMode,
                $decimalPlaces,
            );

        $totalTax = array_sum(
            array_map(
                fn ($component) => $component->taxAmount,
                $calculatedComponents,
            ),
        );

        $roundedTaxableAmount = $this->roundingCalculator->round(
            $taxableAmount,
            $decimalPlaces,
        );

        $totalTax = $this->roundingCalculator->roundTotalTax(
            $totalTax,
            $input->roundingMode,
            $decimalPlaces,
        );

        $totalAmount = match ($input->pricingMode->value) {
            'EXCLUSIVE' => $roundedTaxableAmount + $totalTax,
            'INCLUSIVE' => $this->roundingCalculator->round(
                $input->taxableAmount,
                $decimalPlaces,
            ),
        };

        return new TaxCalculationResult(
            taxableAmount: $roundedTaxableAmount,
            totalTax: $totalTax,
            totalAmount: $totalAmount,
            components: array_map(
                fn ($component) => $component->toArray(),
                $calculatedComponents,
            ),
        );
    }

    protected function validateAmount(float $amount): void
    {
        if ($amount < 0) {
            throw new BusinessException(
                'Taxable amount cannot be negative.',
            );
        }
    }
}