<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Tenant\TenantTeamResolver;
use App\Core\Tenant\TenantManager;

use App\Modules\User\Services\UserService;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Enums\RoleEnum;

use Spatie\Permission\PermissionRegistrar;

class UserServiceCreateAdminTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    public function test_create_admin_assigns_administrator_role_and_clears_team_context(): void
    {
        $tenant = $this->createTestTenant();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => RoleEnum::ADMINISTRATOR->value,
            'guard_name' => 'sanctum',
        ]);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId(null);

        $service = app(UserService::class);

        $admin = $service->createAdmin($tenant, [
            'admin_name' => 'Test Administrator',
            'admin_email' => 'test-admin@example.com',
            'admin_password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'tenant_id' => $tenant->id,
            'email' => 'test-admin@example.com',
        ]);

        $this->assertTrue(
            $admin->fresh()->hasRole($role)
        );

        $this->assertSame(
            $tenant->id,
            $admin->fresh()->roles()->first()->pivot->tenant_id
        );

        // Explicit team ID was cleared.
        // Active tenant context still provides the fallback.
        $this->assertSame(
            $tenant->id,
            $registrar->getPermissionsTeamId()
        );

        // Clear tenant context to verify no explicit team ID remains.
        app(TenantManager::class)->clear();

        $this->assertNull(
            $registrar->getPermissionsTeamId()
        );
    }
}
