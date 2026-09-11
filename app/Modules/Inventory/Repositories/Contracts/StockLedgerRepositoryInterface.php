<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\StockLedger;

interface StockLedgerRepositoryInterface
{
    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(
        array $data
    ): StockLedger;

    /*
    |--------------------------------------------------------------------------
    | Find
    |--------------------------------------------------------------------------
    */

    public function findById(
        int $id
    ): ?StockLedger;

    public function update(
        StockLedger $ledger,
        array $data
    ): StockLedger;

    public function findForUpdate(
        int $id
    ): ?StockLedger;

    /*
    |--------------------------------------------------------------------------
    | Reversal
    |--------------------------------------------------------------------------
    */

    public function findReversalByOriginalLedgerId(
        int $ledgerId
    ): ?StockLedger;

    public function hasReversal(
        int $ledgerId
    ): bool;

    /*
    |--------------------------------------------------------------------------
    | Sequence
    |--------------------------------------------------------------------------
    */

    public function nextSequenceNumber(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): int;
}
