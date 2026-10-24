<?php

namespace App\Modules\GoodsReceiptNote\Services\Calculators;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

class GoodsReceiptNoteCalculator
{
    public function calculateLineTotal(
        float $quantity,
        float $unitCost
    ): float {
        return round(
            $quantity * $unitCost,
            4
        );
    }

    public function calculateItem(
        GoodsReceiptNoteItem $item
    ): array {
        $quantity = (float) $item->quantity_received;
        $unitCost = (float) $item->unit_cost;

        $lineTotal = $this->calculateLineTotal(
            $quantity,
            $unitCost
        );

        $taxAmount = round(
            (float) $item->tax_amount,
            4
        );

        return [
            'line_total' => $lineTotal,
            'tax_amount' => $taxAmount,
        ];
    }

    public function calculateTotalValuation(
        iterable $items
    ): float {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $this->calculateLineTotal(
                (float) $item->quantity_received,
                (float) $item->unit_cost
            );
        }

        return round($total, 4);
    }
}