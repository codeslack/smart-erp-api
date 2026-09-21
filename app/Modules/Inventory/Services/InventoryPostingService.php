<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\StockLedger;

class InventoryPostingService
{
    public function __construct(
        protected InventoryStockInService $stockIn,
        protected InventoryStockOutService $stockOut,
    ) {}

    public function stockIn(
        InventoryMovementData $movement
    ): StockLedger {
        return $this->stockIn->post($movement);
    }

    public function stockOut(
        InventoryMovementData $movement
    ): StockLedger {
        return $this->stockOut->post($movement);
    }
}