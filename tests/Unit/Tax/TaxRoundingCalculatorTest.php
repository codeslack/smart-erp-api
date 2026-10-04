<?php

namespace Tests\Unit\Tax;

use Tests\TestCase;

use App\Modules\Tax\Domain\TaxRoundingCalculator;
use App\Modules\Tax\Domain\TaxComponentCalculationResult;

use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;

class TaxRoundingCalculatorTest extends TestCase
{
    protected TaxRoundingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TaxRoundingCalculator();
    }

    public function test_amount_is_rounded_using_half_up(): void
    {
        $result = $this->calculator->round(
            10.125,
            2,
        );

        $this->assertSame(
            10.13,
            $result,
        );
    }

    public function test_components_are_rounded_individually(): void
    {
        $components = [
            $this->makeComponent(
                taxableAmount: 100.555,
                taxAmount: 18.555,
            ),
        ];

        $result = $this->calculator->roundComponents(
            $components,
            2,
        );

        $this->assertSame(
            100.56,
            $result[0]->taxableAmount,
        );

        $this->assertSame(
            18.56,
            $result[0]->taxAmount,
        );
    }

    public function test_per_component_rounds_components(): void
    {
        $components = [
            $this->makeComponent(
                taxableAmount: 100.555,
                taxAmount: 18.555,
            ),
        ];

        $result = $this->calculator->roundComponentsForMode(
            $components,
            TaxRoundingModeEnum::PER_COMPONENT,
            2,
        );

        $this->assertSame(
            18.56,
            $result[0]->taxAmount,
        );
    }

    public function test_per_line_does_not_round_components(): void
    {
        $components = [
            $this->makeComponent(
                taxableAmount: 100.555,
                taxAmount: 18.555,
            ),
        ];

        $result = $this->calculator->roundComponentsForMode(
            $components,
            TaxRoundingModeEnum::PER_LINE,
            2,
        );

        $this->assertSame(
            18.555,
            $result[0]->taxAmount,
        );
    }

    public function test_per_document_does_not_round_components(): void
    {
        $components = [
            $this->makeComponent(
                taxableAmount: 100.555,
                taxAmount: 18.555,
            ),
        ];

        $result = $this->calculator->roundComponentsForMode(
            $components,
            TaxRoundingModeEnum::PER_DOCUMENT,
            2,
        );

        $this->assertSame(
            18.555,
            $result[0]->taxAmount,
        );
    }

    public function test_total_tax_is_rounded_for_per_component(): void
    {
        $result = $this->calculator->roundTotalTax(
            18.555,
            TaxRoundingModeEnum::PER_COMPONENT,
            2,
        );

        $this->assertSame(
            18.56,
            $result,
        );
    }

    public function test_total_tax_is_rounded_for_per_line(): void
    {
        $result = $this->calculator->roundTotalTax(
            18.555,
            TaxRoundingModeEnum::PER_LINE,
            2,
        );

        $this->assertSame(
            18.56,
            $result,
        );
    }

    public function test_total_tax_is_not_rounded_for_per_document(): void
    {
        $result = $this->calculator->roundTotalTax(
            18.555,
            TaxRoundingModeEnum::PER_DOCUMENT,
            2,
        );

        $this->assertSame(
            18.555,
            $result,
        );
    }

    protected function makeComponent(
        float $taxableAmount,
        float $taxAmount,
    ): TaxComponentCalculationResult
    {
        return new TaxComponentCalculationResult(
            taxComponentId: 1,
            componentType: TaxComponentTypeEnum::CGST->value,
            rate: 9,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            calculationMethod: TaxCalculationMethodEnum::PERCENTAGE->value,
        );
    }
}