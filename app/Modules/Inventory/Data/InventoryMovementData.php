<?php

namespace App\Modules\Inventory\Data;

class InventoryMovementData
{
    public function __construct(

        public readonly int $productId,

        public readonly ?int $productVariantId,

        public readonly int $warehouseId,

        public readonly float $quantity,

        public readonly float $unitCost,

        public readonly string $transactionType,

        public readonly \DateTimeInterface $transactionDate,

        public readonly ?string $referenceType = null,

        public readonly ?int $referenceId = null,

        public readonly ?string $referenceNo = null,

        public readonly ?int $batchId = null,

        public readonly ?int $serialId = null,

        public readonly ?string $remarks = null,
    ) {}
}