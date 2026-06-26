<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

class TenantService
{
    public function __construct(
        private TenantRepositoryInterface $repository,
        private AccountingSetupService $accountingSetupService
    ) {}

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {

            $tenant = $this->repository->create(
                $data
            );

            $this->accountingSetupService
                ->setup($tenant);

            return $tenant;
        });
    }
}