<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Support\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\Inventory\Models\ProductSerial;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;

use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class ProductSerialRepository
    extends BaseRepository
    implements ProductSerialRepositoryInterface
{
    public function __construct(ProductSerial $model)
    {
        parent::__construct($model);
    }

    public function findBySerialNumber(
        string $serialNumber
    ): ?ProductSerial {
        return $this->model
            ->newQuery()
            ->where('serial_number', $serialNumber)
            ->first();
    }

    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when(
                $productVariantId !== null,
                fn ($query) => $query->where(
                    'product_variant_id',
                    $productVariantId
                )
            )
            ->where(
                'status',
                ProductSerialStatusEnum::AVAILABLE
            )
            ->orderBy('id')
            ->get();
    }

    public function findForUpdate(
        int $id
    ): ProductSerial {
        return $this->model
            ->newQuery()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function register(
        array $data
    ): ProductSerial {
        return $this->model
            ->newQuery()
            ->create($data);
    }

    public function markSold(
        ProductSerial $serial
    ): void {
        $serial->update([
            'status' => ProductSerialStatusEnum::SOLD,
            'sold_at' => now(),
        ]);
    }

    public function markAvailable(
        ProductSerial $serial
    ): void {
        $serial->update([
            'status' => ProductSerialStatusEnum::AVAILABLE,
            'sold_at' => null,
        ]);
    }

    public function markReturned(
        ProductSerial $serial
    ): void {
        $serial->update([
            'status' => ProductSerialStatusEnum::RETURNED,
            'sold_at' => null,
        ]);
    }
}