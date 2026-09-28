<?php

namespace Tests\Support;

use App\Modules\Customer\Models\Customer;

trait CreatesCustomer
{
    protected function createCustomer(
        array $attributes = []
    ): Customer {

        return Customer::query()->create(
            array_merge(
                [
                    'name' => 'Test Customer ' . uniqid(),
                    'code' => 'CUS-' . uniqid(),
                    'tenant_id' => tenantId(),
                    'is_active' => true,
                ],
                $attributes
            )
        );
    }
}
