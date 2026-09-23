<?php

namespace Tests\Support;

use Illuminate\Support\Str;

use App\Modules\User\Models\User;

trait CreatesUser
{
    protected function createTestUser(): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER' . uniqid(),
            'tenant_id' => tenantId(),
            'name' => 'Test User' . uniqid(),
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

    }
}
