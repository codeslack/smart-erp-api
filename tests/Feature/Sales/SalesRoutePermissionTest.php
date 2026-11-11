<?php

namespace Tests\Feature\Sales;

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

class SalesRoutePermissionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();

        $this->token = $this->user
            ->createToken('sales-route-permission-test')
            ->plainTextToken;
    }

    public function test_sales_and_sales_return_routes_require_their_own_view_permissions(): void
    {
        $this->grantPermissions(['sale.view']);

        $this->withToken($this->token)
            ->getJson(route('sales.index'))
            ->assertOk();

        $this->withToken($this->token)
            ->getJson(route('sales-returns.index'))
            ->assertForbidden();
    }

    public function test_sales_custom_actions_require_their_explicit_permissions(): void
    {
        $uuid = (string) str()->uuid();

        $this->grantPermissions(['sale.view']);

        $this->withToken($this->token)
            ->postJson(route('sales.confirm', ['uuid' => $uuid]))
            ->assertForbidden();

        $this->grantPermissions(['sale.confirm']);

        $this->withToken($this->token)
            ->postJson(route('sales.confirm', ['uuid' => $uuid]))
            ->assertNotFound();
    }

    public function test_sales_return_custom_actions_require_their_explicit_permissions(): void
    {
        $uuid = (string) str()->uuid();

        $this->grantPermissions(['sales-return.view']);

        $this->withToken($this->token)
            ->postJson(route('sales-returns.approve', ['uuid' => $uuid]))
            ->assertForbidden();

        $this->grantPermissions(['sales-return.approve']);

        $this->withToken($this->token)
            ->postJson(route('sales-returns.approve', ['uuid' => $uuid]))
            ->assertNotFound();
    }

    protected function grantPermissions(array $permissions): void
    {
        $registrar = app(PermissionRegistrar::class);

        app(TenantManager::class)->setTenant($this->tenant);
        $registrar->setPermissionsTeamId($this->tenant->id);

        try {
            $role = Role::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'name' => 'Sales Route Permission Test Role',
                'guard_name' => 'sanctum',
            ]);

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'sanctum',
                ]);

                $role->givePermissionTo($permission);
            }

            $this->user->syncRoles([$role]);
        } finally {
            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
        }
    }
}
