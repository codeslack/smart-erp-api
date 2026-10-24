<?php

namespace App\Modules\PurchaseOrder\Services;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderStatusService
{
    public function refresh(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $purchaseOrder->loadMissing('items');

        if (
            $purchaseOrder->status ===
            PurchaseOrderStatusEnum::CANCELLED->value
        ) {
            return $purchaseOrder;
        }

        if (
            $purchaseOrder->status ===
            PurchaseOrderStatusEnum::CLOSED->value
        ) {
            return $purchaseOrder;
        }

        $hasReceived = false;
        $hasPending = false;

        foreach ($purchaseOrder->items as $item) {
            $ordered = (float) $item->quantity;
            $received = (float) $item->received_quantity;
            $cancelled = (float) $item->cancelled_quantity;

            $pending = $ordered - $received - $cancelled;

            if ($received > 0) {
                $hasReceived = true;
            }

            if ($pending > 0.0001) {
                $hasPending = true;
            }
        }

        if (! $hasReceived) {
            $status = PurchaseOrderStatusEnum::CONFIRMED;
        } elseif ($hasPending) {
            $status = PurchaseOrderStatusEnum::PARTIALLY_RECEIVED;
        } else {
            $status = PurchaseOrderStatusEnum::FULLY_RECEIVED;
        }

        $purchaseOrder->update([
            'status' => $status->value,
            'updated_by' => userId(),
        ]);

        return $purchaseOrder->refresh();
    }
}