<?php

namespace Tests\Feature\Tenant;

use App\Core\Exceptions\BusinessException;
use App\Core\Tenant\TenantManager;
use App\Modules\Rbac\Models\Role;
use App\Modules\Tax\Enums\TaxTypeEnum;
use App\Modules\Tax\Models\Tax;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class TenantOwnershipProtectionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTestTenant();
        $this->tenantB = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenantA);
    }

    public function test_tenant_model_rejects_tenant_id_changes(): void
    {
        $tax = $this->createTax('IMMUTABLE-TAX');

        $tax->tenant_id = $this->tenantB->id;

        $this->assertOwnershipViolation(
            fn () => $tax->save()
        );

        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_tenant_model_rejects_updates_under_another_tenant(): void
    {
        $tax = $this->createTax('CROSS-TENANT-TAX');

        app(TenantManager::class)->setTenant($this->tenantB);

        $tax->name = 'Unauthorized update';

        $this->assertOwnershipViolation(
            fn () => $tax->save()
        );

        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cross tenant tax',
        ]);
    }

    public function test_tenant_authenticatable_rejects_tenant_id_changes(): void
    {
        $user = $this->createTestUser();

        $user->tenant_id = $this->tenantB->id;

        $this->assertOwnershipViolation(
            fn () => $user->save()
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_tenant_authenticatable_rejects_updates_under_another_tenant(): void
    {
        $user = $this->createTestUser();
        $originalName = $user->name;

        app(TenantManager::class)->setTenant($this->tenantB);

        $user->name = 'Unauthorized update';

        $this->assertOwnershipViolation(
            fn () => $user->save()
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $this->tenantA->id,
            'name' => $originalName,
        ]);
    }

    public function test_role_rejects_tenant_id_changes(): void
    {
        $role = $this->createRole();

        $role->tenant_id = $this->tenantB->id;

        $this->assertOwnershipViolation(
            fn () => $role->save()
        );

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_role_rejects_updates_under_another_tenant(): void
    {
        $role = $this->createRole();

        app(TenantManager::class)->setTenant($this->tenantB);

        $role->name = 'Unauthorized role update';

        $this->assertOwnershipViolation(
            fn () => $role->save()
        );

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Test Role',
        ]);
    }

    protected function createTax(string $code): Tax
    {
        return Tax::query()->create([
            'code' => $code,
            'name' => $code === 'CROSS-TENANT-TAX'
                ? 'Cross tenant tax'
                : 'Immutable tenant tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }

    protected function createRole(): Role
    {
        return Role::query()->create([
            'name' => 'Tenant A Test Role',
            'guard_name' => 'sanctum',
        ]);
    }

    protected function assertOwnershipViolation(callable $callback): void
    {
        try {
            $callback();

            $this->fail(
                'The model must reject cross-tenant ownership changes.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'TENANT_OWNERSHIP_VIOLATION',
                $exception->errorCode()
            );
        }
    }
}
