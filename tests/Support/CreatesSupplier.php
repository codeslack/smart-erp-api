<?php

namespace Tests\Support;

use App\Modules\Supplier\Models\Supplier;

trait CreatesSupplier
{
    protected function createSupplier(
        array $attributes = []
    ): Supplier {

        return Supplier::query()->create(
            array_merge(
                [
                    'name' => 'Test Supplier ' . uniqid(),
                    'code' => 'SUP-' . uniqid(),
                    'tenant_id' => tenantId(),
                    'is_active' => true,
                ],
                $attributes
            )
        );
    }
}
