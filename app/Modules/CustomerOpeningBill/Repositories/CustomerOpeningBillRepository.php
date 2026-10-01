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
    private const SCALE = 4;

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

    public function findByCustomerAndBillNo(
        int $customerId,
        string $billNo
    ): ?CustomerOpeningBill {

        return $this->model
            ->newQuery()
            ->where(
                'customer_id',
                $customerId
            )
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
            ->orderBy('id')
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
            ->orderBy('id')
            ->get();
    }

    public function decrementBalance(
        CustomerOpeningBill $openingBill,
        string $amount
    ): CustomerOpeningBill {

        $openingBill = $this->model
            ->newQuery()
            ->whereKey($openingBill->id)
            ->lockForUpdate()
            ->firstOrFail();

        $currentBalance = (string) $openingBill->balance_amount;

        $newBalance = bcsub(
            $currentBalance,
            $amount,
            self::SCALE
        );

        $openingBill->update([
            'balance_amount' => $newBalance,
        ]);

        return $openingBill->fresh();
    }

    public function incrementBalance(
        CustomerOpeningBill $openingBill,
        string $amount
    ): CustomerOpeningBill {

        $openingBill = $this->model
            ->newQuery()
            ->whereKey($openingBill->id)
            ->lockForUpdate()
            ->firstOrFail();

        $currentBalance = (string) $openingBill->balance_amount;

        $newBalance = bcadd(
            $currentBalance,
            $amount,
            self::SCALE
        );

        $openingBill->update([
            'balance_amount' => $newBalance,
        ]);

        return $openingBill->fresh();
    }
}