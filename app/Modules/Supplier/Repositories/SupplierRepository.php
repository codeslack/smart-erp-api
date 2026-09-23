<?php

namespace App\Modules\Supplier\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\BaseRepository;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierRepository
    extends BaseRepository
    implements SupplierRepositoryInterface
{
    public function __construct(
        Supplier $model
    ) {
        parent::__construct($model);
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->model
            ->newQuery()
            ->with([
                'paymentTerm',
                'openingBills',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?Supplier {
        return $this->model
            ->newQuery()
            ->with([
                'paymentTerm',
                'openingBills',
            ])
            ->where('uuid', $uuid)
            ->first();
    }
}
