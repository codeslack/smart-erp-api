<?php

namespace App\Modules\Rbac\Services\Setup;

use App\Core\Modules\ModuleRegistry;
use App\Modules\Rbac\Enums\RoleEnum;
use App\Modules\Rbac\Services\Setup\PermissionMaps\AccountantPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\ManagerPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\PurchaseExecutivePermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\SalesExecutivePermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\ViewerPermissionMap;
use App\Modules\Rbac\Services\Setup\PermissionMaps\WarehouseManagerPermissionMap;

class DefaultRolePermissionMap
{
    public function __construct(
        protected ManagerPermissionMap $manager = new ManagerPermissionMap(),
        protected AccountantPermissionMap $accountant = new AccountantPermissionMap(),
        protected SalesExecutivePermissionMap $salesExecutive = new SalesExecutivePermissionMap(),
        protected PurchaseExecutivePermissionMap $purchaseExecutive = new PurchaseExecutivePermissionMap(),
        protected WarehouseManagerPermissionMap $warehouseManager = new WarehouseManagerPermissionMap(),
        protected ViewerPermissionMap $viewer = new ViewerPermissionMap(),
    ) {}

    /**
     * Get default permissions for all roles.
     *
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        return [
            RoleEnum::ADMINISTRATOR->value => ModuleRegistry::permissions(),
            RoleEnum::MANAGER->value => $this->manager(),
            RoleEnum::ACCOUNTANT->value => $this->accountant(),
            RoleEnum::SALES_EXECUTIVE->value => $this->salesExecutive(),
            RoleEnum::PURCHASE_EXECUTIVE->value => $this->purchaseExecutive(),
            RoleEnum::WAREHOUSE_MANAGER->value => $this->warehouseManager(),
            RoleEnum::VIEWER->value => $this->viewer(),
        ];
    }

    /**
     * Get default permissions for a specific role.
     *
     * @param RoleEnum|string $role
     * @return array<int, string>
     */
    public function get(RoleEnum|string $role): array
    {
        $roleName = $role instanceof RoleEnum
            ? $role->value
            : $role;

        return match ($roleName) {
            RoleEnum::ADMINISTRATOR->value => ModuleRegistry::permissions(),
            RoleEnum::MANAGER->value => $this->manager(),
            RoleEnum::ACCOUNTANT->value => $this->accountant(),
            RoleEnum::SALES_EXECUTIVE->value => $this->salesExecutive(),
            RoleEnum::PURCHASE_EXECUTIVE->value => $this->purchaseExecutive(),
            RoleEnum::WAREHOUSE_MANAGER->value => $this->warehouseManager(),
            RoleEnum::VIEWER->value => $this->viewer(),
            default => [],
        };
    }

    public function manager(): array
    {
        return $this->manager->permissions();
    }

    public function accountant(): array
    {
        return $this->accountant->permissions();
    }

    public function salesExecutive(): array
    {
        return $this->salesExecutive->permissions();
    }

    public function purchaseExecutive(): array
    {
        return $this->purchaseExecutive->permissions();
    }

    public function warehouseManager(): array
    {
        return $this->warehouseManager->permissions();
    }

    public function viewer(): array
    {
        return $this->viewer->permissions();
    }
}
