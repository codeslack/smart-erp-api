<?php

namespace App\Modules\Sales\Services\Calculators;

class SaleCalculator
{
    public function calculate(array $items, array $headerData = []): array
    {
        $subtotal = 0.0;
        $itemDiscounts = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
            $itemDiscounts += (float) ($item['discount_amount'] ?? 0);
            $taxAmount += (float) ($item['tax_amount'] ?? 0);
        }

        $headerDiscount = (float) ($headerData['discount_amount'] ?? $headerData['discount'] ?? 0);
        $totalDiscount = $itemDiscounts + $headerDiscount;

        $chargesSubtotal = (float) ($headerData['charges_subtotal'] ?? 0);
        $chargesTax = (float) ($headerData['charges_tax_amount'] ?? 0);
        $roundingAmount = (float) ($headerData['rounding_amount'] ?? 0);

        $grandTotal = max(
            0,
            round(
                $subtotal - $headerDiscount + $taxAmount + $chargesSubtotal + $chargesTax + $roundingAmount,
                4
            )
        );

        $paidAmount = (float) ($headerData['paid_amount'] ?? 0);
        $dueAmount = max(0, round($grandTotal - $paidAmount, 4));

        return [
            'subtotal' => round($subtotal, 4),
            'discount_amount' => round($totalDiscount, 4),
            'discount' => round($totalDiscount, 4),
            'charges_subtotal' => round($chargesSubtotal, 4),
            'charges_tax_amount' => round($chargesTax, 4),
            'tax_amount' => round($taxAmount, 4),
            'tax' => round($taxAmount, 4),
            'rounding_amount' => round($roundingAmount, 4),
            'grand_total' => round($grandTotal, 4),
            'paid_amount' => round($paidAmount, 4),
            'due_amount' => round($dueAmount, 4),
        ];
    }
}
