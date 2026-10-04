<?php

namespace Tests\Feature\Tax;

use App\Core\Tenant\TenantManager;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxTypeEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Models\Tax;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenant;
use Tests\TestCase;


class TaxTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
    }

    public function test_tax_groups_are_isolated_by_tenant(): void
    {
        $tenantA = $this->tenant;

        $tenantATax = Tax::query()->create([
            'tenant_id' => $tenantA->id,
            'code' => 'TAX-A',
            'name' => 'Tenant A GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $tenantAGroup = TaxGroup::query()->create([
            'tenant_id' => $tenantA->id,
            'tax_id' => $tenantATax->id,
            'code' => 'GST18-A',
            'name' => 'Tenant A GST 18%',
            'usage' => TaxUsageEnum::BOTH->value,
            'direction' => TaxDirectionEnum::INPUT->value,
            'treatment' => TaxTreatmentEnum::STANDARD->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $tenantB = $this->createTestTenant();

        $tenantBTax = Tax::query()->create([
            'tenant_id' => $tenantB->id,
            'code' => 'TAX-B',
            'name' => 'Tenant B GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $tenantBGroup = TaxGroup::query()->create([
            'tenant_id' => $tenantB->id,
            'tax_id' => $tenantBTax->id,
            'code' => 'GST18-B',
            'name' => 'Tenant B GST 18%',
            'usage' => TaxUsageEnum::BOTH->value,
            'direction' => TaxDirectionEnum::INPUT->value,
            'treatment' => TaxTreatmentEnum::STANDARD->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        /*
         * createTestTenant() activates Tenant B.
         * Switch back to Tenant A and verify only A's records are visible.
         */
        app(TenantManager::class)->setTenant($tenantA);

        $groups = TaxGroup::query()->get();

        $this->assertCount(1, $groups);
        $this->assertSame($tenantAGroup->id, $groups->first()->id);
        $this->assertSame($tenantA->id, $groups->first()->tenant_id);

        $this->assertFalse(
            $groups->contains(
                fn (TaxGroup $group): bool =>
                    $group->id === $tenantBGroup->id,
            ),
        );
    }

    public function test_tax_records_are_isolated_by_tenant(): void
    {
        $tenantA = $this->tenant;

        $tenantATax = Tax::query()->create([
            'tenant_id' => $tenantA->id,
            'code' => 'TAX-A',
            'name' => 'Tenant A GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $tenantB = $this->createTestTenant();

        $tenantBTax = Tax::query()->create([
            'tenant_id' => $tenantB->id,
            'code' => 'TAX-B',
            'name' => 'Tenant B GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        app(TenantManager::class)->setTenant($tenantA);

        $taxes = Tax::query()->get();

        $this->assertCount(1, $taxes);
        $this->assertSame($tenantATax->id, $taxes->first()->id);
        $this->assertSame($tenantA->id, $taxes->first()->tenant_id);

        $this->assertFalse(
            $taxes->contains(
                fn (Tax $tax): bool =>
                    $tax->id === $tenantBTax->id,
            ),
        );
    }
}