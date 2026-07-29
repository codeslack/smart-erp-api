<?php

namespace App\Modules\Unit\Services;

use App\Modules\Unit\Models\Unit;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tenant\Contracts\TenantSetupInterface;

class UnitSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {

        foreach (
            $this->defaultUnits()
            as $unit
        ) {

            Unit::query()
                ->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name'      => $unit['name'],
                    ],
                    [
                        'code' => nextSystemNumber(
                            SystemNumberTypeEnum::UNIT,
                            $tenant->id
                        ),
                        'short_name'  => $unit['short_name'],
                        'description' => $unit['description'],
                        'is_active'   => true,
                    ]
                );
        }
    }

    protected function defaultUnits(): array
    {
        return [

            [
                'code' => 'UNT-001',
                'name' => 'Piece',
                'short_name' => 'PCS',
                'description' => 'Single piece',
            ],

            [
                'code' => 'UNT-002',
                'name' => 'Box',
                'short_name' => 'BOX',
                'description' => 'Box',
            ],

            [
                'code' => 'UNT-003',
                'name' => 'Packet',
                'short_name' => 'PKT',
                'description' => 'Packet',
            ],

            [
                'code' => 'UNT-004',
                'name' => 'Dozen',
                'short_name' => 'DZN',
                'description' => 'Dozen',
            ],

            [
                'code' => 'UNT-005',
                'name' => 'Kilogram',
                'short_name' => 'KG',
                'description' => 'Kilogram',
            ],

            [
                'code' => 'UNT-006',
                'name' => 'Gram',
                'short_name' => 'GM',
                'description' => 'Gram',
            ],

            [
                'code' => 'UNT-007',
                'name' => 'Liter',
                'short_name' => 'LTR',
                'description' => 'Liter',
            ],

            [
                'code' => 'UNT-008',
                'name' => 'Milliliter',
                'short_name' => 'ML',
                'description' => 'Milliliter',
            ],

            [
                'code' => 'UNT-009',
                'name' => 'Meter',
                'short_name' => 'MTR',
                'description' => 'Meter',
            ],
        ];
    }
}