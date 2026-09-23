<?php

namespace App\Modules\CustomerOpeningBill\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;

/**
 * @extends BaseRepository<CustomerOpeningBill>
 */
class CustomerOpeningBillRepository
    extends BaseRepository
    implements CustomerOpeningBillRepositoryInterface
{
    public function __construct(
        CustomerOpeningBill $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByUuid(
        string $uuid
    ): ?CustomerOpeningBill {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function findByBillNo(
        string $billNo
    ): ?CustomerOpeningBill {

        return $this->model
            ->newQuery()
            ->where(
                'bill_no',
                $billNo
            )
            ->first();
    }

    public function findByCustomer(
        int $customerId
    ): Collection {

        return $this->model
            ->newQuery()
            ->where(
                'customer_id',
                $customerId
            )
            ->orderBy(
                'bill_date'
            )
            ->get();
    }

    public function outstandingBills(
        int $customerId
    ): Collection {

        return $this->model
            ->newQuery()
            ->where(
                'customer_id',
                $customerId
            )
            ->where(
                'balance_amount',
                '>',
                0
            )
            ->orderBy(
                'due_date'
            )
            ->orderBy(
                'bill_date'
            )
            ->get();
    }
}