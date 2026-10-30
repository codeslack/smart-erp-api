<?php

namespace App\Modules\SalesOrder\Services\Calculators;

class SalesOrderCalculator
{
    /**
     * Calculate Sales Order header totals from calculated line items.
     *
     * @param array $items
     * @param float $additionalCharge
     * @param float $roundingAdjustment
     * @param float $headerDiscount
     * @return array
     */
    public function calculate(
        array $items,
        float $additionalCharge = 0,
        float $roundingAdjustment = 0,
        float $headerDiscount = 0
    ): array {
        $subtotal = 0.0;
        $itemDiscounts = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal_amount'] ?? 0);
            $itemDiscounts += (float) ($item['discount_amount'] ?? 0);
            $taxTotal += (float) ($item['tax_total_amount'] ?? 0);
        }

        $totalDiscount = $itemDiscounts + $headerDiscount;

        $grandTotal = max(
            0,
            round(
                $subtotal - $headerDiscount + $taxTotal + $additionalCharge + $roundingAdjustment,
                4
            )
        );

        return [
            'subtotal_amount' => round($subtotal, 4),
            'discount_amount' => round($totalDiscount, 4),
            'additional_charge_amount' => round($additionalCharge, 4),
            'tax_total_amount' => round($taxTotal, 4),
            'rounding_adjustment' => round($roundingAdjustment, 4),
            'grand_total_amount' => round($grandTotal, 4),
        ];
    }
}
