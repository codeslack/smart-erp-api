<?php

namespace App\Modules\PurchaseOrder\Services\Calculators;

class PurchaseOrderCalculator
{
    /**
     * Calculate Purchase Order header totals
     * from already-calculated item values.
     *
     * @param array $items
     * @param float $additionalCharge
     * @param float $roundingAdjustment
     * @return array
     */
    public function calculate(
        array $items,
        float $additionalCharge = 0,
        float $roundingAdjustment = 0
    ): array {
        $subtotal = 0;
        $discount = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal_amount'] ?? 0);
            $discount += (float) ($item['discount_amount'] ?? 0);
            $taxTotal += (float) ($item['tax_total_amount'] ?? 0);
        }

        $grandTotal =
            $subtotal
            + $taxTotal
            + $additionalCharge
            + $roundingAdjustment;

        return [
            'subtotal' => round($subtotal, 4),
            'discount' => round($discount, 4),
            'additional_charge' => round($additionalCharge, 4),
            'tax_total' => round($taxTotal, 4),
            'rounding_adjustment' => round($roundingAdjustment, 4),
            'grand_total' => round($grandTotal, 4),
        ];
    }
}