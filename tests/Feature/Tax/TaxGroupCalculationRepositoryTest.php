<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tax\Repositories\TaxGroupRepository;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

class TaxGroupCalculationRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
    }

    public function test_effective_active_group_is_returned(): void
    {
        $group = $this->createGroup(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-12-31',
        );

        $result = $this->repository()->findForCalculation(
            $group->id,
            '2026-10-01',
        );

        $this->assertNotNull($result);
        $this->assertSame($group->id, $result->id);
    }

    public function test_group_before_effective_from_is_not_returned(): void
    {
        $group = $this->createGroup(
            effectiveFrom: '2026-10-01',
            effectiveTo: null,
        );

        $result = $this->repository()->findForCalculation(
            $group->id,
            '2026-09-30',
        );

        $this->assertNull($result);
    }

    public function test_group_after_effective_to_is_not_returned(): void
    {
        $group = $this->createGroup(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-09-30',
        );

        $result = $this->repository()->findForCalculation(
            $group->id,
            '2026-10-01',
        );

        $this->assertNull($result);
    }

    public function test_inactive_group_is_not_returned(): void
    {
        $group = $this->createGroup(
            effectiveFrom: '2026-01-01',
            effectiveTo: null,
            isActive: false,
        );

        $result = $this->repository()->findForCalculation(
            $group->id,
            '2026-10-01',
        );

        $this->assertNull($result);
    }

    public function test_only_effective_active_components_are_loaded(): void
    {
        $group = $this->createGroup();

        $active = $this->createComponent('CGST', true);

        $inactive = $this->createComponent('SGST', false);

        $expired = $this->createComponent('IGST', true);

        $this->createGroupComponent(
            $group,
            $active,
            '2026-01-01',
            null,
        );

        $this->createGroupComponent(
            $group,
            $inactive,
            '2026-01-01',
            null,
        );

        $this->createGroupComponent(
            $group,
            $expired,
            '2026-01-01',
            '2026-09-30',
        );

        $result = $this->repository()->findForCalculation(
            $group->id,
            '2026-10-01',
        );

        $this->assertNotNull($result);
        $this->assertCount(1, $result->components);
        $this->assertSame(
            TaxComponentTypeEnum::CGST->value,
            $result->components->first()->taxComponent->component_type,
        );
    }

    public function test_other_tenant_group_is_not_returned(): void
    {
        $group = $this->createGroup();

        $otherTenant = $this->createTestTenant();

        $otherGroup = TaxGroup::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'tax_id' => $group->tax_id,
            'code' => 'OTHER-TAX-GROUP',
            'name' => 'Other Tenant Tax Group',
            'usage' => TaxUsageEnum::BOTH->value,
            'direction' => TaxDirectionEnum::INPUT->value,
            'treatment' => TaxTreatmentEnum::STANDARD->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $result = $this->repository()->findForCalculation(
            $otherGroup->id,
            '2026-10-01',
        );

        $this->assertNull($result);
    }

    protected function repository(): TaxGroupRepository
    {
        return app(TaxGroupRepository::class);
    }

    protected function createGroup(
        string $effectiveFrom = '2026-01-01',
        ?string $effectiveTo = null,
        bool $isActive = true,
    ): TaxGroup {
        return TaxGroup::query()->create([
            'tax_id' => $this->createTaxId(),
            'code' => 'GST-' . uniqid(),
            'name' => 'Test GST Group',
            'usage' => TaxUsageEnum::BOTH->value,
            'direction' => TaxDirectionEnum::INPUT->value,
            'treatment' => TaxTreatmentEnum::STANDARD->value,
            'is_active' => $isActive,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);
    }

    protected function createTaxId(): int
    {
        return \App\Modules\Tax\Models\Tax::query()->create([
            'code' => 'TAX-' . uniqid(),
            'name' => 'Test GST',
            'tax_type' => 'GST',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ])->id;
    }

    protected function createComponent(
        string $type,
        bool $isActive,
    ): TaxComponent {
        return TaxComponent::query()->create([
            'code' => 'TC-' . uniqid(),
            'name' => $type,
            'component_type' => $type,
            'is_active' => $isActive,
        ]);
    }

    protected function createGroupComponent(
        TaxGroup $group,
        TaxComponent $component,
        string $effectiveFrom,
        ?string $effectiveTo,
    ): TaxGroupComponent {
        return TaxGroupComponent::query()->create([
            'tax_group_id' => $group->id,
            'tax_component_id' => $component->id,
            'rate' => 9,
            'calculation_method' => TaxCalculationMethodEnum::PERCENTAGE->value,
            'is_active' => true,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'sort_order' => 1,
        ]);
    }
}