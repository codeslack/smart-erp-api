<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

class TenantService
{
    public function __construct(
        private TenantRepositoryInterface $repository
    ) {}

    public function create(array $data): Tenant
    {
        return DB::transaction(
            fn () => $this->repository->create($data)
        );
    }
}