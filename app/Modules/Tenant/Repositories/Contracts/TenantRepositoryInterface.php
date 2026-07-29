<?php

namespace App\Modules\Tenant\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\Tenant\Models\Tenant;

interface TenantRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findBySlug(
        string $slug
    ): ?Tenant;

    public function findByDomain(
        string $domain
    ): ?Tenant;

    public function existsBySlug(
        string $slug
    ): bool;
}