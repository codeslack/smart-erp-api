<?php

// app/Modules/SupplierOpeningBill/Services/SupplierOpeningBillService.php

namespace App\Modules\SupplierOpeningBill\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;
use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

class SupplierOpeningBillService extends BaseService
{
    public function __construct(
        protected SupplierOpeningBillRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate(
            $perPage
        );
    }

    public function create(
        array $data
    ): SupplierOpeningBill {
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
        SupplierOpeningBill $openingBill,
        array $data
    ): SupplierOpeningBill {
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
        SupplierOpeningBill $openingBill
    ): bool {
        return DB::transaction(
            function () use ($openingBill) {

                if (
                    (float) $openingBill->balance_amount
                    <
                    (float) $openingBill->amount
                ) {
                    throw new BusinessException(
                        'Supplier opening bill cannot be deleted after payment allocation.'
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
    ): ?SupplierOpeningBill {
        return $this->repository->findById(
            $id
        );
    }

    public function findByUuid(
        string $uuid
    ): ?SupplierOpeningBill {
        return $this->repository->findByUuid(
            $uuid
        );
    }

    public function findBySupplierAndBillNo(
        int $supplierId,
        string $billNo
    ): ?SupplierOpeningBill {
        return $this->repository->findBySupplierAndBillNo(
            $supplierId,
            $billNo
        );
    }

    public function findBySupplier(
        int $supplierId
    ): Collection {
        return $this->repository->findBySupplier(
            $supplierId
        );
    }

    public function outstandingBills(
        int $supplierId
    ): Collection {
        return $this->repository->outstandingBills(
            $supplierId
        );
    }

    protected function validateAmount(
        array $data,
        ?SupplierOpeningBill $openingBill = null
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
