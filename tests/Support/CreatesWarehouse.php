<?php

namespace Tests\Support;

use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

trait CreatesWarehouse
{
    protected function createWarehouse(): Warehouse
    {

        return Warehouse::create(
            array_merge(
                [
                    'tenant_id' => tenantId(),

                    'name' => 'Test Warehouse',

                    'code' => nextSystemNumber(
                        SystemNumberTypeEnum::WAREHOUSE
                    ),

                    'is_active' => true,
                ],
            )
        );
    }
}
