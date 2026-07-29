<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Services\UserService;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

class TenantService
{
    public function __construct(
        protected TenantRepositoryInterface $repository,
        protected TenantSetupService $setup,
        protected UserService $users,
    ) {}

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {

            $data['code'] = nextSystemNumber(
                SystemNumberTypeEnum::COMPANY
            );

            logger()->info('Creating Tenant');

            $tenant = $this->repository->create(
                $data
            );

            $tenant->refresh();

            logger()->info('Running Tenant Setup');

            $this->setup->setup(
                $tenant
            );

            logger()->info('Admin User Setup');
            $this->users->createAdmin(
                $tenant,
                $data
            );

            logger()->info('Admin User Setup Complete');

            return $tenant;
        });
    }
}
