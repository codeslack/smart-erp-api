<?php

namespace App\Modules\SupplierOpeningBill\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

/**
 * @extends BaseRepository<SupplierOpeningBill>
 */
class SupplierOpeningBillRepository
    extends BaseRepository
    implements SupplierOpeningBillRepositoryInterface
{
    private const SCALE = 4;

    public function __construct(
        SupplierOpeningBill $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByUuid(
        string $uuid
    ): ?SupplierOpeningBill {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function findBySupplierAndBillNo(
        int $supplierId,
        string $billNo
    ): ?SupplierOpeningBill {

        return $this->model
            ->newQuery()
            ->where(
                'supplier_id',
                $supplierId
            )
            ->where(
                'bill_no',
                $billNo
            )
            ->first();
    }

    public function findBySupplier(
        int $supplierId
    ): Collection {

        return $this->model
            ->newQuery()
            ->where(
                'supplier_id',
                $supplierId
            )
            ->orderBy(
                'bill_date'
            )
            ->orderBy('id')
            ->get();
    }

    public function outstandingBills(
        int $supplierId
    ): Collection {

        return $this->model
            ->newQuery()
            ->where(
                'supplier_id',
                $supplierId
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
        SupplierOpeningBill $openingBill,
        string $amount
    ): SupplierOpeningBill {

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
        SupplierOpeningBill $openingBill,
        string $amount
    ): SupplierOpeningBill {

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