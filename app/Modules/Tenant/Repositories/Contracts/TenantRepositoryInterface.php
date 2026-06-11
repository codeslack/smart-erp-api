<?php

namespace App\Modules\Tenant\Repositories\Contracts;

use App\Modules\Tenant\Models\Tenant;

interface TenantRepositoryInterface
{
    public function create(array $data): Tenant;

    public function find(int $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;
}