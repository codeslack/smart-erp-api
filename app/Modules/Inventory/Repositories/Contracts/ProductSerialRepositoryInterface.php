<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use Illuminate\Support\Collection;

use App\Modules\Inventory\Models\ProductSerial;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface ProductSerialRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findBySerialNumber(
        string $serialNumber
    ): ?ProductSerial;

    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection;

    public function findForUpdate(
        int $id
    ): ProductSerial;

    public function register(
        array $data
    ): ProductSerial;

    public function markSold(
        ProductSerial $serial
    ): void;

    public function markAvailable(
        ProductSerial $serial
    ): void;

    public function markReturned(
        ProductSerial $serial
    ): void;
}