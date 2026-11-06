<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Tenant\TenantManager;

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
        protected TenantManager $tenantManager,
    ) {}

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {

            $previousTenant = $this->tenantManager->getTenant();

            $data['code'] = nextSystemNumber(
                SystemNumberTypeEnum::COMPANY
            );

            logger()->info('Creating Tenant');

            $tenantData = collect($data)
                ->only([
                    'name',
                    'slug',
                    'domain',
                    'business_type',
                    'is_active',
                ])
                ->all();

            $tenantData['code'] = $data['code'];

            $tenant = $this->repository->create($tenantData);

            $tenant->refresh();

            $this->tenantManager->setTenant($tenant);

            try {

                logger()->info('Running Tenant Setup');

                $this->setup->setup($tenant);

                logger()->info('Admin User Setup');

                $this->users->createAdmin(
                    $tenant,
                    $data
                );

                logger()->info('Admin User Setup Complete');

                return $tenant;

            } finally {

                if ($previousTenant) {
                    $this->tenantManager->setTenant($previousTenant);
                } else {
                    $this->tenantManager->clear();
                }
            }
        });
    }
}
