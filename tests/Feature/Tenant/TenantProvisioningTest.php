<?php

namespace Tests\Feature\Tenant;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\DB;

use Spatie\Permission\PermissionRegistrar;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Enums\BusinessTypeEnum;
use App\Modules\Tenant\Services\TenantService;

use App\Modules\User\Models\User;

use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Enums\RoleEnum;
use App\Modules\Rbac\Seeders\PermissionSeeder;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->clear();

        app(PermissionRegistrar::class)
            ->setPermissionsTeamId(null);

        $this->seed(PermissionSeeder::class);
    }

    public function test_tenant_provisioning_creates_roles_permissions_and_admin(): void
    {
        $service = app(TenantService::class);

        /*
        |--------------------------------------------------------------------------
        | Create First Tenant
        |--------------------------------------------------------------------------
        */

        $tenantA = $service->create(
            $this->tenantData('first')
        );

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantA->id,
            'code' => $tenantA->code,
        ]);

        $this->assertStringStartsWith(
            'CMP-',
            $tenantA->code
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Seven Default Roles
        |--------------------------------------------------------------------------
        */

        $rolesA = Role::query()
            ->where('tenant_id', $tenantA->id)
            ->get();

        $this->assertCount(7, $rolesA);

        $this->assertEqualsCanonicalizing(
            RoleEnum::values(),
            $rolesA->pluck('name')->all()
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Administrator Permissions
        |--------------------------------------------------------------------------
        */

        $adminRoleA = $rolesA->firstWhere(
            'name',
            RoleEnum::ADMINISTRATOR->value
        );

        $availablePermissions = Permission::query()
            ->where('guard_name', 'sanctum')
            ->pluck('name')
            ->all();

        $adminPermissions = $adminRoleA
            ->permissions()
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            $availablePermissions,
            $adminPermissions
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Viewer Is Read-Only
        |--------------------------------------------------------------------------
        */

        $viewerRoleA = $rolesA->firstWhere(
            'name',
            RoleEnum::VIEWER->value
        );

        $viewerPermissions = $viewerRoleA
            ->permissions()
            ->pluck('name')
            ->all();

        $this->assertNotEmpty($viewerPermissions);

        foreach ($viewerPermissions as $permission) {
            $this->assertStringEndsWith(
                '.view',
                $permission
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Administrator User
        |--------------------------------------------------------------------------
        */

        $admin = DB::table('users')
            ->where('tenant_id', $tenantA->id)
            ->where('email', 'admin-first@example.com')
            ->first();

        $this->assertNotNull($admin);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $adminRoleA->id,
            'model_type' => User::class,
            'model_id' => $admin->id,
            'tenant_id' => $tenantA->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Verify Tenant Context Cleanup
        |--------------------------------------------------------------------------
        */

        $this->assertNull(
            app(TenantManager::class)->getTenant()
        );

        $this->assertNull(
            app(PermissionRegistrar::class)
                ->getPermissionsTeamId()
        );

        /*
        |--------------------------------------------------------------------------
        | Create Second Tenant
        |--------------------------------------------------------------------------
        */

        $tenantB = $service->create(
            $this->tenantData('second')
        );

        $rolesB = Role::query()
            ->where('tenant_id', $tenantB->id)
            ->get();

        $this->assertCount(7, $rolesB);

        /*
        |--------------------------------------------------------------------------
        | Verify Tenant Isolation
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            7,
            Role::query()
                ->where('tenant_id', $tenantA->id)
                ->count()
        );

        $this->assertSame(
            7,
            Role::query()
                ->where('tenant_id', $tenantB->id)
                ->count()
        );

        $this->assertNotEquals(
            $rolesA->pluck('id')->all(),
            $rolesB->pluck('id')->all()
        );

        $this->assertSame(
            $availablePermissions,
            Permission::query()
                ->where('guard_name', 'sanctum')
                ->pluck('name')
                ->all()
        );

        $this->assertNull(
            app(TenantManager::class)->getTenant()
        );

        $this->assertNull(
            app(PermissionRegistrar::class)
                ->getPermissionsTeamId()
        );
    }

    protected function tenantData(string $suffix): array
    {
        return [
            'name' => "Test Company {$suffix}",
            'slug' => "test-company-{$suffix}",
            'domain' => "test-{$suffix}.local",
            'business_type' => BusinessTypeEnum::COMPUTER,
            'is_active' => true,

            'admin_name' => 'Administrator',
            'admin_email' => "admin-{$suffix}@example.com",
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ];
    }
}