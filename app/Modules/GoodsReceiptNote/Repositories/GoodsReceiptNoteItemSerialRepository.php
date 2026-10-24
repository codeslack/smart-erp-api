<?php

namespace App\Modules\GoodsReceiptNote\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemSerial;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemSerialRepositoryInterface;

/**
 * @extends BaseRepository<GoodsReceiptNoteItemSerial>
 */
class GoodsReceiptNoteItemSerialRepository extends BaseRepository
    implements GoodsReceiptNoteItemSerialRepositoryInterface
{
    public function __construct(
        GoodsReceiptNoteItemSerial $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItemSerial {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function findBySerialNo(
        string $serialNo
    ): ?GoodsReceiptNoteItemSerial {

        return $this->model
            ->newQuery()
            ->where(
                'serial_number',
                $serialNo
            )
            ->first();
    }

    public function existsSerialNo(
        string $serialNo,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()

            ->where(
                'serial_number',
                $serialNo
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