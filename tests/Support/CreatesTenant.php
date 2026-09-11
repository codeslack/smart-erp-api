<?php

namespace Tests\Support;

use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Enums\BusinessTypeEnum;
use App\Modules\Tenant\Models\Tenant;

trait CreatesTenant
{
    protected function createTestTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Company',
            'code' => 'test-company-' . uniqid(),
            'slug' => 'test-company-' . uniqid(),
            'domain' => 'test-' . uniqid() . '.local',
            'business_type' => BusinessTypeEnum::COMPUTER,
            'is_active' => true,
        ]);

        app(TenantManager::class)
            ->setTenant($tenant);

        return $tenant;
    }
}