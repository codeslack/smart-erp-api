<?php

namespace Tests\Unit\Tax;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Domain\TaxApplicabilityResult;
use App\Modules\Tax\Domain\TaxCalculationResult;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Services\TaxApplicabilityService;
use App\Modules\Tax\Services\TaxCalculationService;
use App\Modules\Tax\Services\TaxTransactionCalculationService;
use Mockery;
use Tests\TestCase;

class TaxTransactionCalculationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_resolves_applicability_and_calculates_tax(): void
    {
        $applicabilityResult = new TaxApplicabilityResult(
            taxApplicabilityRuleId: 10,
            taxGroupId: 20,
            usage: TaxUsageEnum::SALES->value,
            direction: TaxDirectionEnum::OUTPUT->value,
            treatment: 'STANDARD',
        );

        $calculationResult = new TaxCalculationResult(
            taxableAmount: 1000.00,
            totalTax: 180.00,
            totalAmount: 1180.00,
            components: [],
        );

        $applicability = Mockery::mock(
            TaxApplicabilityService::class,
        );

        $applicability
            ->shouldReceive('resolve')
            ->once()
            ->with(Mockery::type(TaxApplicabilityInput::class))
            ->andReturn($applicabilityResult);

        $calculation = Mockery::mock(
            TaxCalculationService::class,
        );

        $calculation
            ->shouldReceive('calculate')
            ->once()
            ->withArgs(function ($input, $decimalPlaces) {
                return $input->taxGroupId === 20
                    && $input->taxableAmount === 1000.00
                    && $input->pricingMode === TaxPricingModeEnum::EXCLUSIVE
                    && $input->roundingMode === TaxRoundingModeEnum::PER_COMPONENT
                    && $input->calculationDate === '2026-06-01'
                    && $decimalPlaces === 2;
            })
            ->andReturn($calculationResult);

        $service = new TaxTransactionCalculationService(
            applicability: $applicability,
            calculation: $calculation,
        );

        $result = $service->calculate(
            applicabilityInput: $this->input(),
            taxableAmount: 1000.00,
            pricingMode: TaxPricingModeEnum::EXCLUSIVE,
            roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
            decimalPlaces: 2,
        );

        $this->assertSame(
            $applicabilityResult,
            $result->applicability,
        );

        $this->assertSame(
            $calculationResult,
            $result->calculation,
        );
    }

    public function test_it_passes_the_applicable_tax_group_to_calculation(): void
    {
        $applicabilityResult = new TaxApplicabilityResult(
            taxApplicabilityRuleId: 15,
            taxGroupId: 99,
            usage: TaxUsageEnum::PURCHASE->value,
            direction: TaxDirectionEnum::INPUT->value,
            treatment: 'STANDARD',
        );

        $applicability = Mockery::mock(
            TaxApplicabilityService::class,
        );

        $applicability
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($applicabilityResult);

        $calculation = Mockery::mock(
            TaxCalculationService::class,
        );

        $calculation
            ->shouldReceive('calculate')
            ->once()
            ->withArgs(function ($input) {
                return $input->taxGroupId === 99;
            })
            ->andReturn(
                new TaxCalculationResult(
                    taxableAmount: 500.00,
                    totalTax: 60.00,
                    totalAmount: 560.00,
                    components: [],
                ),
            );

        $service = new TaxTransactionCalculationService(
            applicability: $applicability,
            calculation: $calculation,
        );

        $result = $service->calculate(
            applicabilityInput: $this->input(),
            taxableAmount: 500.00,
            pricingMode: TaxPricingModeEnum::EXCLUSIVE,
            roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
        );

        $this->assertSame(
            99,
            $result->applicability->taxGroupId,
        );
    }

    protected function input(): TaxApplicabilityInput
    {
        return new TaxApplicabilityInput(
            usage: TaxUsageEnum::SALES,
            direction: TaxDirectionEnum::OUTPUT,
            sourceCountryCode: 'IN',
            sourceStateCode: 'WB',
            destinationCountryCode: 'IN',
            destinationStateCode: 'WB',
            placeOfSupplyStateCode: 'WB',
            sourceRegistrationRequired: true,
            destinationRegistrationRequired: true,
            calculationDate: '2026-06-01',
        );
    }
}