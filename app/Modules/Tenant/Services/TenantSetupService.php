<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Rbac\Services\PermissionSetupService;
use App\Modules\Rbac\Services\RoleSetupService;
use App\Modules\Settings\Services\SettingsSetupService;
use App\Modules\Tenant\Models\Tenant;

class TenantSetupService
{
    public function __construct(
        protected SettingsSetupService $settings,
        protected AccountingSetupService $accounting,
        protected RoleSetupService $roles,
        protected PermissionSetupService $permissions,
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
    }
}