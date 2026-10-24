<?php

namespace App\Modules\GoodsReceiptNote\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;

interface GoodsReceiptNoteItemBatchRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItemBatch;

    public function existsBatchNo(
        int $goodsReceiptNoteItemId,
        string $batchNo,
        ?int $ignoreId = null
    ): bool;
}