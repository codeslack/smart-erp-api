<?php

namespace App\Modules\Tenant\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

/**
 * @extends BaseRepository<Tenant>
 */
class TenantRepository 
    extends BaseRepository
    implements TenantRepositoryInterface
{
    public function __construct(
        Tenant $tenant
    ) {
        $this->model = $tenant;
    }

    public function findBySlug(
        string $slug
    ): ?Tenant {
        return $this->model
            ->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function findByDomain(
        string $domain
    ): ?Tenant {
        return $this->model
            ->newQuery()
            ->where('domain', $domain)
            ->first();
    }

    public function existsBySlug(
        string $slug
    ): bool {
        return $this->model
            ->newQuery()
            ->where('slug', $slug)
            ->exists();
    }    
}