<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Tenant\Models\Tenant;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tenant\Contracts\TenantSetupInterface;

use App\Modules\Warehouse\Models\Warehouse;

class WarehouseSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {

        Warehouse::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Main Warehouse',
            ],
            [
                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::WAREHOUSE,
                    $tenant->id
                ),

                'is_default' => true,

                'is_active' => true,
            ]
        );
    }
}