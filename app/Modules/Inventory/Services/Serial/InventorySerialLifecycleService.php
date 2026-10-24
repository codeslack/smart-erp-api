<?php

namespace App\Modules\Inventory\Services\Serial;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class InventorySerialLifecycleService
{
    public function __construct(
        protected ProductSerialRepositoryInterface $serials,
    ) {}

    public function activate(
        ProductSerial $serial
    ): ProductSerial {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::DRAFT,
            message: 'Only draft serials can be activated.',
        );

        return $this->serials->update(
            $serial,
            [
                'status' => ProductSerialStatusEnum::AVAILABLE,
            ]
        );
    }

    public function deleteDraft(
        ProductSerial $serial
    ): bool {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::DRAFT,
            message: 'Only draft serials can be deleted.',
        );

        return $this->serials->delete($serial);
    }

    public function restoreDraft(
        ProductSerial $serial
    ): ProductSerial {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::AVAILABLE,
            message: 'Only available serial can be restored to draft.',
        );

        return $this->serials->update(
            $serial,
            [
                'status' => ProductSerialStatusEnum::DRAFT,
                'sold_at' => null,
            ]
        );
    }

    protected function validateStatus(
        ProductSerial $serial,
        ProductSerialStatusEnum $expected,
        string $message,
    ): void {
        if ($serial->status !== $expected) {
            throw new BusinessException($message);
        }
    }
}