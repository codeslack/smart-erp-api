<?php

namespace App\Modules\Tax\Services;

use App\Modules\Tax\Models\Tax;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tax\Enums\TaxTypeEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;
use App\Modules\Tenant\Contracts\TenantSetupInterface;

class TaxSetupService implements TenantSetupInterface
{
    public function setup(Tenant $tenant): void
    {
        $tax = Tax::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'GST',
            ],
            [
                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::TAX,
                    $tenant->id
                ),
                'tax_type' => TaxTypeEnum::GST->value,
                'description' => 'Goods and Services Tax',
                'is_active' => true,
                'effective_from' => now()->toDateString(),
            ]
        );

        $components = $this->createComponents($tenant);

        $this->createGroups(
            $tenant,
            $tax,
            $components
        );
    }

    /**
     * @return array<string, TaxComponent>
     */
    protected function createComponents(Tenant $tenant): array
    {
        $definitions = [
            'CGST' => TaxComponentTypeEnum::CGST->value,
            'SGST' => TaxComponentTypeEnum::SGST->value,
            'IGST' => TaxComponentTypeEnum::IGST->value,
            'CESS' => TaxComponentTypeEnum::CESS->value,
        ];

        $components = [];

        foreach ($definitions as $name => $type) {
            $components[$name] = TaxComponent::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                ],
                [
                    'code' => nextSystemNumber(
                        SystemNumberTypeEnum::TAX_COMPONENT,
                        $tenant->id
                    ),
                    'component_type' => $type,
                    'is_active' => true,
                ]
            );
        }

        return $components;
    }

    /**
     * @param array<string, TaxComponent> $components
     */
    protected function createGroups(
        Tenant $tenant,
        Tax $tax,
        array $components
    ): void {
        foreach ($this->defaultRates() as $rate => $data) {
            $this->createGroup(
                $tenant,
                $tax,
                $components,
                $rate,
                'INTRA',
                TaxDirectionEnum::INPUT->value,
                $data['cgst'],
                $data['sgst']
            );

            $this->createGroup(
                $tenant,
                $tax,
                $components,
                $rate,
                'INTER',
                TaxDirectionEnum::INPUT->value,
                0,
                0,
                $data['igst']
            );

            $this->createGroup(
                $tenant,
                $tax,
                $components,
                $rate,
                'INTRA',
                TaxDirectionEnum::OUTPUT->value,
                $data['cgst'],
                $data['sgst']
            );

            $this->createGroup(
                $tenant,
                $tax,
                $components,
                $rate,
                'INTER',
                TaxDirectionEnum::OUTPUT->value,
                0,
                0,
                $data['igst']
            );
        }
    }

    /**
     * @param array<string, TaxComponent> $components
     */
    protected function createGroup(
        Tenant $tenant,
        Tax $tax,
        array $components,
        int $rate,
        string $scope,
        string $direction,
        float $cgst,
        float $sgst,
        float $igst = 0
    ): void {
        $group = TaxGroup::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => $this->groupCode($rate, $scope, $direction),
            ],
            [
                'tax_id' => $tax->id,
                'name' => $this->groupName($rate, $scope, $direction),
                'usage' => TaxUsageEnum::BOTH->value,
                'direction' => $direction,
                'treatment' => TaxTreatmentEnum::STANDARD->value,
                'is_active' => true,
                'effective_from' => now()->toDateString(),
            ]
        );

        $this->createGroupComponent(
            $tenant,
            $group,
            $components['CGST'],
            $cgst
        );

        $this->createGroupComponent(
            $tenant,
            $group,
            $components['SGST'],
            $sgst
        );

        $this->createGroupComponent(
            $tenant,
            $group,
            $components['IGST'],
            $igst
        );
    }

    protected function createGroupComponent(
        Tenant $tenant,
        TaxGroup $group,
        TaxComponent $component,
        float $rate
    ): void {
        TaxGroupComponent::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tax_group_id' => $group->id,
                'tax_component_id' => $component->id,
            ],
            [
                'rate' => $rate,
                'calculation_method' =>
                    TaxCalculationMethodEnum::PERCENTAGE->value,
                'is_active' => true,
                'effective_from' => now()->toDateString(),
                'sort_order' => $this->componentSortOrder(
                    $component->component_type
                ),
            ]
        );
    }

    protected function groupCode(
        int $rate,
        string $scope,
        string $direction
    ): string {
        $prefix = $direction === TaxDirectionEnum::INPUT->value
            ? 'IN'
            : 'OUT';

        return "GST{$rate}-{$scope}-{$prefix}";
    }

    protected function groupName(
        int $rate,
        string $scope,
        string $direction
    ): string {
        $directionName = $direction === TaxDirectionEnum::INPUT->value
            ? 'Input'
            : 'Output';

        return "GST {$rate}% {$scope} {$directionName}";
    }

    protected function componentSortOrder(string $type): int
    {
        return match ($type) {
            TaxComponentTypeEnum::CGST->value => 1,
            TaxComponentTypeEnum::SGST->value => 2,
            TaxComponentTypeEnum::IGST->value => 3,
            TaxComponentTypeEnum::CESS->value => 4,
            default => 99,
        };
    }

    /**
     * @return array<int, array{
     *     cgst: float,
     *     sgst: float,
     *     igst: float
     * }>
     */
    protected function defaultRates(): array
    {
        return [
            0 => [
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
            ],
            5 => [
                'cgst' => 2.5,
                'sgst' => 2.5,
                'igst' => 5,
            ],
            12 => [
                'cgst' => 6,
                'sgst' => 6,
                'igst' => 12,
            ],
            18 => [
                'cgst' => 9,
                'sgst' => 9,
                'igst' => 18,
            ],
            28 => [
                'cgst' => 14,
                'sgst' => 14,
                'igst' => 28,
            ],
        ];
    }
}