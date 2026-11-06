<?php

namespace Tests\Feature\User;

use App\Core\Tenant\TenantManager;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class UserPermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenantA;

    protected User $tenantAUser;

    protected string $tenantAToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTestTenant();

        $this->tenantAUser = $this->createTestUser();

        $this->tenantAToken = $this->tenantAUser
            ->createToken('permission-test')
            ->plainTextToken;
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $this->withToken($this->tenantAToken)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_users(): void
    {
        $this->grantPermission(
            $this->tenantAUser,
            $this->tenantA,
            'user.view'
        );

        $this->withToken($this->tenantAToken)
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_permission_from_another_tenant_is_not_authorized(): void
    {
        // Create Tenant B and assign user.view to its role.
        $tenantB = $this->createTestTenant();

        $this->grantPermission(
            null,
            $tenantB,
            'user.view'
        );

        // Restore Tenant A context.
        app(TenantManager::class)
            ->setTenant($this->tenantA);

        // Tenant A user must still be forbidden.
        $this->withToken($this->tenantAToken)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    protected function grantPermission(
        ?User $user,
        Tenant $tenant,
        string $permissionName
    ): void {
        $registrar = app(PermissionRegistrar::class);

        app(TenantManager::class)
            ->setTenant($tenant);

        $registrar->setPermissionsTeamId(
            $tenant->id
        );

        try {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'sanctum',
            ]);

            $role = Role::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Permission Test Role',
                'guard_name' => 'sanctum',
            ]);

            $role->givePermissionTo($permission);

            if ($user) {
                $user->assignRole($role);
            }

        } finally {
            $registrar->setPermissionsTeamId(null);

            $registrar->forgetCachedPermissions();
        }
    }
}