<?php

namespace App\Modules\Inventory\Data;

use App\Core\Exceptions\BusinessException;

final readonly class InventoryReversalData
{
    public function __construct(
        public int $stockLedgerId,
        public \DateTimeInterface $reversalDate,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $referenceNo = null,
        public ?string $remarks = null,
    ) {
        if ($this->stockLedgerId <= 0) {
            throw new BusinessException(
                'Stock ledger ID must be greater than zero.',
                'INVALID_STOCK_LEDGER'
            );
        }
    }
}