<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tax\Services\TaxSetupService;
use App\Modules\Tax\Enums\TaxDirectionEnum;

class TaxSetupTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenant);
    }

    public function test_default_gst_tax_setup_is_created(): void
    {
        app(TaxSetupService::class)->setup($this->tenant);

        $this->assertDatabaseHas('taxes', [
            'tenant_id' => $this->tenant->id,
            'name' => 'GST',
            'tax_type' => 'gst',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('taxes', 1);

        $this->assertDatabaseCount('tax_components', 4);

        foreach ([
            'CGST',
            'SGST',
            'IGST',
            'CESS',
        ] as $component) {
            $this->assertDatabaseHas('tax_components', [
                'tenant_id' => $this->tenant->id,
                'name' => $component,
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseCount('tax_groups', 20);
    }

    public function test_default_gst_group_rates_are_correct(): void
    {
        app(TaxSetupService::class)->setup($this->tenant);

        $expectedRates = [
            0 => [
                'CGST' => 0,
                'SGST' => 0,
                'IGST' => 0,
            ],
            5 => [
                'CGST' => 2.5,
                'SGST' => 2.5,
                'IGST' => 5,
            ],
            12 => [
                'CGST' => 6,
                'SGST' => 6,
                'IGST' => 12,
            ],
            18 => [
                'CGST' => 9,
                'SGST' => 9,
                'IGST' => 18,
            ],
            28 => [
                'CGST' => 14,
                'SGST' => 14,
                'IGST' => 28,
            ],
        ];

        foreach ($expectedRates as $rate => $components) {
            foreach ([
                TaxDirectionEnum::INPUT->value,
                TaxDirectionEnum::OUTPUT->value,
            ] as $direction) {
                $intraGroup = TaxGroup::query()
                    ->where('tenant_id', $this->tenant->id)
                    ->where('code', "GST{$rate}-INTRA-{$this->directionCode($direction)}")
                    ->firstOrFail();

                $interGroup = TaxGroup::query()
                    ->where('tenant_id', $this->tenant->id)
                    ->where('code', "GST{$rate}-INTER-{$this->directionCode($direction)}")
                    ->firstOrFail();

                $this->assertComponentRate(
                    $intraGroup,
                    'CGST',
                    $components['CGST']
                );

                $this->assertComponentRate(
                    $intraGroup,
                    'SGST',
                    $components['SGST']
                );

                $this->assertComponentRate(
                    $intraGroup,
                    'IGST',
                    0
                );

                $this->assertComponentRate(
                    $interGroup,
                    'CGST',
                    0
                );

                $this->assertComponentRate(
                    $interGroup,
                    'SGST',
                    0
                );

                $this->assertComponentRate(
                    $interGroup,
                    'IGST',
                    $components['IGST']
                );
            }
        }
    }

    public function test_default_tax_setup_is_idempotent(): void
    {
        $setup = app(TaxSetupService::class);

        $setup->setup($this->tenant);

        $this->assertDatabaseCount('taxes', 1);
        $this->assertDatabaseCount('tax_components', 4);
        $this->assertDatabaseCount('tax_groups', 20);
        $this->assertDatabaseCount('tax_group_components', 60);

        $setup->setup($this->tenant);

        $this->assertDatabaseCount('taxes', 1);
        $this->assertDatabaseCount('tax_components', 4);
        $this->assertDatabaseCount('tax_groups', 20);
        $this->assertDatabaseCount('tax_group_components', 60);
    }

    protected function assertComponentRate(
        TaxGroup $group,
        string $componentName,
        float|int $expectedRate
    ): void {
        $component = TaxComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', $componentName)
            ->firstOrFail();

        $groupComponent = TaxGroupComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('tax_group_id', $group->id)
            ->where('tax_component_id', $component->id)
            ->firstOrFail();

        $this->assertEquals(
            $expectedRate,
            (float) $groupComponent->rate,
            "{$group->name}: {$componentName} rate mismatch."
        );
    }

    protected function directionCode(string $direction): string
    {
        return $direction === TaxDirectionEnum::INPUT->value
            ? 'IN'
            : 'OUT';
    }
}