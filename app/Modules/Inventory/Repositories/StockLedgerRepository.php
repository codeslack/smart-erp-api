<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class StockLedgerRepository
    implements StockLedgerRepositoryInterface
{
    public function __construct(
        protected StockLedger $model
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(
        array $data
    ): StockLedger {

        return $this->model
            ->newQuery()
            ->create($data);
    }

    /*
    |--------------------------------------------------------------------------
    | Find
    |--------------------------------------------------------------------------
    */

    public function findById(
        int $id
    ): ?StockLedger {

        return $this->model
            ->newQuery()
            ->find($id);
    }

    public function findForUpdate(
        int $id
    ): ?StockLedger {

        return $this->model
            ->newQuery()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function update(
        StockLedger $ledger,
        array $data
    ): StockLedger {

        $ledger->update($data);

        return $ledger->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Reversal
    |--------------------------------------------------------------------------
    */

    public function findReversalByOriginalLedgerId(
        int $ledgerId
    ): ?StockLedger {

        return $this->model
            ->newQuery()
            ->where(
                'reversal_of_ledger_id',
                $ledgerId
            )
            ->first();
    }

    public function hasReversal(
        int $ledgerId
    ): bool {

        return $this->model
            ->newQuery()
            ->where(
                'reversal_of_ledger_id',
                $ledgerId
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Next Sequence Number
    |--------------------------------------------------------------------------
    |
    | Sequence is maintained per:
    |
    | Tenant
    | Product
    | Variant
    | Warehouse
    |
    | Used inside a database transaction.
    |
    */

    public function nextSequenceNumber(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): int {

        return (
            $this->model
                ->newQuery()
                ->where(
                    'product_id',
                    $productId
                )
                ->when(
                    $productVariantId !== null,
                    fn ($query) =>
                        $query->where(
                            'product_variant_id',
                            $productVariantId
                        ),
                    fn ($query) =>
                        $query->whereNull(
                            'product_variant_id'
                        )
                )
                ->where(
                    'warehouse_id',
                    $warehouseId
                )
                ->lockForUpdate()
                ->max('sequence_no')
            ?? 0
        ) + 1;
    }
}