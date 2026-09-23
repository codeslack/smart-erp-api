<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\PaymentTerm\Services\PaymentTermSetupService;
use App\Modules\Rbac\Services\PermissionSetupService;
use App\Modules\Rbac\Services\RoleSetupService;
use App\Modules\Settings\Services\SettingsSetupService;
use App\Modules\Unit\Services\UnitSetupService;
use App\Modules\Warehouse\Services\WarehouseSetupService;

class TenantSetupService
{
    public function __construct(
        protected SettingsSetupService $settings,
        protected AccountingSetupService $accounting,
        protected RoleSetupService $roles,
        protected PermissionSetupService $permissions,
        protected UnitSetupService $units,
        protected WarehouseSetupService $warehouses,
        protected PaymentTermSetupService $paymentTerms,
    ) {
    }

    public function setup(
        Tenant $tenant
    ): void {

        logger()->info('Settings Setup');
        $this->settings->setup(
            $tenant
        );

        logger()->info('Accounting Setup');
        $this->accounting->setup(
            $tenant
        );

        logger()->info('Role Setup');
        $this->roles->setup(
            $tenant
        );

        logger()->info('Permission Setup');
        $this->permissions->setup(
            $tenant
        );

        logger()->info('Unit Setup');
        $this->units->setup(
            $tenant
        );

        logger()->info('Warehouse Setup');
        $this->warehouses->setup(
            $tenant
        );

        logger()->info('Payment Term Setup');
        $this->paymentTerms->setup(
            $tenant
        );
    }
}