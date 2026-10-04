<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Models\Tax;
use App\Modules\Tax\Models\TaxApplicabilityRule;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;

class TaxApplicabilityRuleRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTenant();
    }

    public function test_it_finds_matching_applicability_rule(): void
    {
        $group = $this->createTaxGroup();

        $rule = TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'place_of_supply_state_code' => 'WB',
            'source_registration_required' => true,
            'destination_registration_required' => true,
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            new TaxApplicabilityInput(
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
            ),
        );

        $this->assertNotNull($result);
        $this->assertSame($rule->id, $result->id);
    }

    public function test_it_ignores_rule_before_effective_date(): void
    {
        $group = $this->createTaxGroup();

        TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'place_of_supply_state_code' => 'WB',
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2027-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNull($result);
    }

    public function test_it_ignores_rule_after_effective_date(): void
    {
        $group = $this->createTaxGroup();

        TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'place_of_supply_state_code' => 'WB',
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNull($result);
    }

    public function test_it_ignores_inactive_rule(): void
    {
        $group = $this->createTaxGroup();

        TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'place_of_supply_state_code' => 'WB',
            'priority' => 1,
            'is_active' => false,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNull($result);
    }

    public function test_null_location_fields_act_as_wildcards(): void
    {
        $group = $this->createTaxGroup();

        $rule = TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => null,
            'destination_country_code' => 'IN',
            'destination_state_code' => null,
            'place_of_supply_state_code' => null,
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            new TaxApplicabilityInput(
                usage: TaxUsageEnum::SALES,
                direction: TaxDirectionEnum::OUTPUT,
                sourceCountryCode: 'IN',
                sourceStateCode: 'DL',
                destinationCountryCode: 'IN',
                destinationStateCode: 'MH',
                placeOfSupplyStateCode: 'MH',
                sourceRegistrationRequired: null,
                destinationRegistrationRequired: null,
                calculationDate: '2026-06-01',
            ),
        );

        $this->assertNotNull($result);
        $this->assertSame($rule->id, $result->id);
    }

    public function test_it_uses_lower_priority_number_first(): void
    {
        $group = $this->createTaxGroup();

        TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'priority' => 10,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $firstRule = TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNotNull($result);
        $this->assertSame($firstRule->id, $result->id);
    }

    public function test_it_requires_matching_tax_group_usage_and_direction(): void
    {
        $group = $this->createTaxGroup(
            usage: TaxUsageEnum::PURCHASE,
            direction: TaxDirectionEnum::INPUT,
        );

        TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNull($result);
    }

    public function test_it_loads_the_tax_group_for_the_applicable_rule(): void
    {
        $group = $this->createTaxGroup();

        $rule = TaxApplicabilityRule::create([
            'tax_group_id' => $group->id,
            'source_country_code' => 'IN',
            'source_state_code' => 'WB',
            'destination_country_code' => 'IN',
            'destination_state_code' => 'WB',
            'place_of_supply_state_code' => 'WB',
            'priority' => 1,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->repository()->findApplicable(
            $this->input(),
        );

        $this->assertNotNull($result);
        $this->assertSame($rule->id, $result->id);
        $this->assertNotNull($result->taxGroup);
        $this->assertSame($group->id, $result->taxGroup->id);
    }

    protected function repository(): TaxApplicabilityRuleRepositoryInterface
    {
        return app(TaxApplicabilityRuleRepositoryInterface::class);
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
            sourceRegistrationRequired: null,
            destinationRegistrationRequired: null,
            calculationDate: '2026-06-01',
        );
    }

    protected function createTaxGroup(
        TaxUsageEnum $usage = TaxUsageEnum::SALES,
        TaxDirectionEnum $direction = TaxDirectionEnum::OUTPUT,
    ): TaxGroup {
        $tax = Tax::create([
            'code' => 'GST-TEST-' . uniqid(),
            'name' => 'GST Test',
            'tax_type' => 'GST',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        return TaxGroup::create([
            'tax_id' => $tax->id,
            'code' => 'GROUP-' . uniqid(),
            'name' => 'Test Group',
            'usage' => $usage->value,
            'direction' => $direction->value,
            'treatment' => 'STANDARD',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }
}