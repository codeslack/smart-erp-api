<?php

namespace Tests\Unit\Rbac;

use App\Core\Modules\ModuleRegistry;
use App\Modules\Rbac\Enums\RoleEnum;
use App\Modules\Rbac\Services\Setup\DefaultRolePermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\AccountantPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\ManagerPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\PurchaseExecutivePermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\SalesExecutivePermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\ViewerPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\WarehouseManagerPermissionMap;
use Tests\TestCase;

class DefaultRolePermissionMapTest extends TestCase
{
    public function test_all_returns_permissions_for_every_role(): void
    {
        $map = new DefaultRolePermissionMap();

        $all = $map->all();

        $this->assertCount(
            count(RoleEnum::cases()),
            $all
        );

        foreach (RoleEnum::cases() as $role) {
            $this->assertArrayHasKey(
                $role->value,
                $all
            );

            $this->assertNotEmpty(
                $all[$role->value],
                "Permissions for {$role->value} should not be empty."
            );

            $this->assertSame(
                array_unique($all[$role->value]),
                $all[$role->value],
                "Permissions for {$role->value} should not contain duplicates."
            );
        }

        $this->assertSame(
            ModuleRegistry::permissions(),
            $all[RoleEnum::ADMINISTRATOR->value]
        );
    }

    public function test_each_permission_map_matches_central_coordinator(): void
    {
        $map = new DefaultRolePermissionMap();

        $this->assertSame(
            (new ManagerPermissionMap())->permissions(),
            $map->manager()
        );

        $this->assertSame(
            (new AccountantPermissionMap())->permissions(),
            $map->accountant()
        );

        $this->assertSame(
            (new SalesExecutivePermissionMap())->permissions(),
            $map->salesExecutive()
        );

        $this->assertSame(
            (new PurchaseExecutivePermissionMap())->permissions(),
            $map->purchaseExecutive()
        );

        $this->assertSame(
            (new WarehouseManagerPermissionMap())->permissions(),
            $map->warehouseManager()
        );

        $this->assertSame(
            (new ViewerPermissionMap())->permissions(),
            $map->viewer()
        );
    }

    public function test_get_resolves_all_roles_by_enum_and_string(): void
    {
        $map = new DefaultRolePermissionMap();

        foreach (RoleEnum::cases() as $role) {
            $this->assertSame(
                $map->get($role),
                $map->get($role->value)
            );

            $this->assertSame(
                $map->all()[$role->value],
                $map->get($role)
            );
        }

        $this->assertSame(
            [],
            $map->get('non_existent_role')
        );
    }

    public function test_viewer_only_contains_view_permissions(): void
    {
        $permissions = (new ViewerPermissionMap())->permissions();

        $this->assertNotEmpty($permissions);

        foreach ($permissions as $permission) {
            $this->assertStringEndsWith(
                '.view',
                $permission
            );
        }
    }

    public function test_container_resolves_default_role_permission_map(): void
    {
        $resolved = app(DefaultRolePermissionMap::class);

        $this->assertInstanceOf(
            DefaultRolePermissionMap::class,
            $resolved
        );

        $this->assertCount(
            count(RoleEnum::cases()),
            $resolved->all()
        );
    }
}
