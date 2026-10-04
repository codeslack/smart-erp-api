<?php

namespace Tests\Unit\Tax;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Models\TaxApplicabilityRule;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;
use App\Modules\Tax\Services\TaxApplicabilityService;
use Mockery;
use Tests\TestCase;

class TaxApplicabilityServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_returns_the_applicable_rule(): void
    {
        $rule = new TaxApplicabilityRule();

        $rule->setAttribute('id', 10);
        $rule->setAttribute('tax_group_id', 20);

        $taxGroup = new TaxGroup();

        $taxGroup->setAttribute('id', 20);
        $taxGroup->setAttribute(
            'usage',
            TaxUsageEnum::SALES->value,
        );
        $taxGroup->setAttribute(
            'direction',
            TaxDirectionEnum::OUTPUT->value,
        );
        $taxGroup->setAttribute(
            'treatment',
            TaxTreatmentEnum::STANDARD->value,
        );

        $rule->setRelation('taxGroup', $taxGroup);

        $repository = Mockery::mock(
            TaxApplicabilityRuleRepositoryInterface::class,
        );

        $repository
            ->shouldReceive('findApplicable')
            ->once()
            ->with(Mockery::type(TaxApplicabilityInput::class))
            ->andReturn($rule);

        $service = new TaxApplicabilityService($repository);

        $result = $service->resolve($this->input());

        $this->assertSame(
            10,
            $result->taxApplicabilityRuleId,
        );

        $this->assertSame(
            20,
            $result->taxGroupId,
        );

        $this->assertSame(
            TaxUsageEnum::SALES->value,
            $result->usage,
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $result->direction,
        );

        $this->assertSame(
            TaxTreatmentEnum::STANDARD->value,
            $result->treatment,
        );
    }

    public function test_it_throws_when_no_rule_is_found(): void
    {
        $repository = Mockery::mock(
            TaxApplicabilityRuleRepositoryInterface::class,
        );

        $repository
            ->shouldReceive('findApplicable')
            ->once()
            ->with(Mockery::type(TaxApplicabilityInput::class))
            ->andReturn(null);

        $service = new TaxApplicabilityService($repository);

        $this->expectException(BusinessException::class);

        $service->resolve($this->input());
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