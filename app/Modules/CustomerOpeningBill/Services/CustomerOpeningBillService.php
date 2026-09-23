<?php

namespace App\Modules\CustomerOpeningBill\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;

class CustomerOpeningBillService extends BaseService
{
    public function __construct(
        protected CustomerOpeningBillRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage);
    }

    public function create(
        array $data
    ): CustomerOpeningBill {
        return DB::transaction(
            function () use ($data) {

                $this->validateAmount(
                    $data
                );

                return $this->repository->create(
                    $data
                );
            }
        );
    }

    public function update(
        CustomerOpeningBill $openingBill,
        array $data
    ): CustomerOpeningBill {
        return DB::transaction(
            function () use (
                $openingBill,
                $data
            ) {

                $this->validateAmount(
                    $data,
                    $openingBill
                );

                return $this->repository->update(
                    $openingBill,
                    $data
                );
            }
        );
    }

    public function delete(
        CustomerOpeningBill $openingBill
    ): bool {
        return DB::transaction(
            function () use ($openingBill) {

                if (
                    (float) $openingBill->balance_amount
                    <
                    (float) $openingBill->amount
                ) {
                    throw new BusinessException(
                        'Customer opening bill cannot be deleted after payment allocation.'
                    );
                }

                return $this->repository->delete(
                    $openingBill
                );
            }
        );
    }

    public function findById(
        int $id
    ): ?CustomerOpeningBill {
        return $this->repository->findById(
            $id
        );
    }

    public function findByUuid(
        string $uuid
    ): ?CustomerOpeningBill {
        return $this->repository->findByUuid(
            $uuid
        );
    }

    public function findByBillNo(
        string $billNo
    ): ?CustomerOpeningBill {
        return $this->repository->findByBillNo(
            $billNo
        );
    }

    public function findByCustomer(
        int $customerId
    ): Collection {
        return $this->repository->findByCustomer(
            $customerId
        );
    }

    public function outstandingBills(
        int $customerId
    ): Collection {
        return $this->repository->outstandingBills(
            $customerId
        );
    }

    protected function validateAmount(
        array $data,
        ?CustomerOpeningBill $openingBill = null
    ): void {
        $amount = array_key_exists(
            'amount',
            $data
        )
            ? (float) $data['amount']
            : (float) $openingBill?->amount;

        $balanceAmount = array_key_exists(
            'balance_amount',
            $data
        )
            ? (float) $data['balance_amount']
            : (float) $openingBill?->balance_amount;

        if ($amount <= 0) {
            throw new BusinessException(
                'Opening bill amount must be greater than zero.'
            );
        }

        if ($balanceAmount < 0) {
            throw new BusinessException(
                'Opening bill outstanding amount cannot be negative.'
            );
        }

        if ($balanceAmount > $amount) {
            throw new BusinessException(
                'Opening bill outstanding amount cannot exceed bill amount.'
            );
        }
    }
}
