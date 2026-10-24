<?php

namespace App\Modules\GoodsReceiptNote\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemBatchRepositoryInterface;

/**
 * @extends BaseRepository<GoodsReceiptNoteItemBatch>
 */
class GoodsReceiptNoteItemBatchRepository extends BaseRepository
    implements GoodsReceiptNoteItemBatchRepositoryInterface
{
    public function __construct(
        GoodsReceiptNoteItemBatch $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItemBatch {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function existsBatchNo(
        int $goodsReceiptNoteItemId,
        string $batchNo,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()

            ->where(
                'goods_receipt_note_item_id',
                $goodsReceiptNoteItemId
            )

            ->where(
                'batch_no',
                $batchNo
            )

            ->when(
                $ignoreId,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )

            ->exists();
    }
}