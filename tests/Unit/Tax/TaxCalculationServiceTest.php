<?php

namespace Tests\Unit\Tax;

use Tests\TestCase;
use Mockery;
use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Domain\TaxComponentCalculator;
use App\Modules\Tax\Domain\TaxPricingCalculator;
use App\Modules\Tax\Domain\TaxRoundingCalculator;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;
use App\Modules\Tax\Repositories\Contracts\TaxGroupRepositoryInterface;
use App\Modules\Tax\Services\TaxCalculationService;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tax\Models\TaxComponent;

class TaxCalculationServiceTest extends TestCase
{
    public function test_exclusive_gst_is_calculated_correctly(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 9),
            $this->groupComponent('SGST', 9),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(10000.0, $result->taxableAmount);
        $this->assertSame(1800.0, $result->totalTax);
        $this->assertSame(11800.0, $result->totalAmount);

        $this->assertCount(2, $result->components);
        $this->assertSame('CGST', $result->components[0]['component_type']);
        $this->assertSame('SGST', $result->components[1]['component_type']);
        $this->assertSame(900.0, $result->components[0]['tax_amount']);
        $this->assertSame(900.0, $result->components[1]['tax_amount']);
    }

    public function test_inclusive_gst_is_calculated_correctly(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 9),
            $this->groupComponent('SGST', 9),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 11800,
                pricingMode: TaxPricingModeEnum::INCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(10000.0, $result->taxableAmount);
        $this->assertSame(1800.0, $result->totalTax);
        $this->assertSame(11800.0, $result->totalAmount);
    }

    public function test_missing_tax_group_is_rejected(): void
    {
        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturnNull();

        $service = $this->service($repository);

        $this->expectException(\Throwable::class);

        $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );
    }

    public function test_igst_is_calculated_correctly(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('IGST', 18),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(10000.0, $result->taxableAmount);
        $this->assertSame(1800.0, $result->totalTax);
        $this->assertSame(11800.0, $result->totalAmount);

        $this->assertCount(1, $result->components);
        $this->assertSame('IGST', $result->components[0]['component_type']);
        $this->assertSame(18.0, $result->components[0]['rate']);
        $this->assertSame(1800.0, $result->components[0]['tax_amount']);
    }

    public function test_zero_rate_produces_no_tax(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 0),
            $this->groupComponent('SGST', 0),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(10000.0, $result->taxableAmount);
        $this->assertSame(0.0, $result->totalTax);
        $this->assertSame(10000.0, $result->totalAmount);
    }

    public function test_twelve_percent_gst_is_split_between_cgst_and_sgst(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 6),
            $this->groupComponent('SGST', 6),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 25000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(1500.0, $result->components[0]['tax_amount']);
        $this->assertSame(1500.0, $result->components[1]['tax_amount']);
        $this->assertSame(3000.0, $result->totalTax);
        $this->assertSame(28000.0, $result->totalAmount);
    }

    public function test_fixed_component_is_calculated_for_exclusive_pricing(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent(
                'CESS',
                100,
                'FIXED',
            ),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(100.0, $result->totalTax);
        $this->assertSame(10100.0, $result->totalAmount);
        $this->assertSame(
            'FIXED',
            $result->components[0]['calculation_method'],
        );
    }

    public function test_inclusive_pricing_with_fractional_tax_is_rounded(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 2.5),
            $this->groupComponent('SGST', 2.5),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $result = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 1050,
                pricingMode: TaxPricingModeEnum::INCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $this->assertSame(1000.0, $result->taxableAmount);
        $this->assertSame(50.0, $result->totalTax);
        $this->assertSame(1050.0, $result->totalAmount);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $service = $this->service($repository);

        $this->expectException(\Throwable::class);

        $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: -100,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );

        $repository->shouldNotHaveReceived('findForCalculation');
    }

    public function test_empty_tax_group_components_are_rejected(): void
    {
        $group = $this->taxGroup([]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->once()
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $this->expectException(\Throwable::class);

        $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 10000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
        );
    }


    public function test_rounding_modes_apply_at_the_configured_calculation_level(): void
    {
        $group = $this->taxGroup([
            $this->groupComponent('CGST', 9),
            $this->groupComponent('SGST', 9),
        ]);

        $repository = Mockery::mock(TaxGroupRepositoryInterface::class);

        $repository
            ->shouldReceive('findForCalculation')
            ->times(3)
            ->with(1, '2026-10-01')
            ->andReturn($group);

        $service = $this->service($repository);

        $perComponent = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 1.03,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: '2026-10-01',
            ),
            2,
        );

        $perLine = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 1.03,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_LINE,
                calculationDate: '2026-10-01',
            ),
            2,
        );

        $perDocument = $service->calculate(
            new TaxCalculationInput(
                taxGroupId: 1,
                taxableAmount: 1.03,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_DOCUMENT,
                calculationDate: '2026-10-01',
            ),
            2,
        );

        $this->assertSame(0.18, $perComponent->totalTax);
        $this->assertEqualsWithDelta(0.19, $perLine->totalTax, 0.000001);
        $this->assertEqualsWithDelta(0.1854, $perDocument->totalTax, 0.000001);

        $this->assertEqualsWithDelta(
            0.0927,
            $perDocument->components[0]['tax_amount'],
            0.000001,
        );
        $this->assertEqualsWithDelta(
            1.2154,
            $perDocument->totalAmount,
            0.000001,
        );
    }

    protected function service(
        TaxGroupRepositoryInterface $repository,
    ): TaxCalculationService {
        return new TaxCalculationService(
            groups: $repository,
            componentCalculator: new TaxComponentCalculator(),
            pricingCalculator: new TaxPricingCalculator(),
            roundingCalculator: new TaxRoundingCalculator(),
        );
    }

    protected function taxGroup(array $components): TaxGroup
    {
        $group = new TaxGroup();

        $group->setRelation(
            'components',
            collect($components),
        );

        return $group;
    }

    protected function groupComponent(
        string $componentType,
        float $rate,
        string $calculationMethod = 'PERCENTAGE',
    ): TaxGroupComponent {
        $component = new TaxComponent();

        $component->component_type = $componentType;

        $groupComponent = new TaxGroupComponent();

        $groupComponent->tax_component_id = random_int(1, 999);
        $groupComponent->rate = $rate;
        $groupComponent->calculation_method = $calculationMethod;

        $groupComponent->setRelation(
            'taxComponent',
            $component,
        );

        return $groupComponent;
    }
}