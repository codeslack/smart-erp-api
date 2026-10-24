<?php

namespace App\Modules\GoodsReceiptNote\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemSerial;

interface GoodsReceiptNoteItemSerialRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItemSerial;

    public function findBySerialNo(
        string $serialNo
    ): ?GoodsReceiptNoteItemSerial;

    public function existsSerialNo(
        string $serialNo,
        ?int $ignoreId = null
    ): bool;
}