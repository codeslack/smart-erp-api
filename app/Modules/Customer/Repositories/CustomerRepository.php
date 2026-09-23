<?php

namespace App\Modules\Customer\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\BaseRepository;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository
    extends BaseRepository
    implements CustomerRepositoryInterface
{
    public function __construct(
        Customer $model
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
    ): ?Customer {
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
