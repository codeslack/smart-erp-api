<?php

namespace App\Modules\SalesReturn\Services\Calculators;

use App\Modules\SalesReturn\Enums\SalesReturnRefundType;

class SalesReturnCalculator
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
        $roundingAmount = (float) ($headerData['rounding_amount'] ?? 0);

        $grandTotal = max(
            0,
            round(
                $subtotal - $headerDiscount + $taxAmount + $roundingAmount,
                4
            )
        );

        $refundType = $headerData['refund_type'] ?? SalesReturnRefundType::CREDIT_NOTE->value;
        if ($refundType instanceof SalesReturnRefundType) {
            $refundType = $refundType->value;
        }

        $creditedAmount = ($refundType === SalesReturnRefundType::CREDIT_NOTE->value) ? $grandTotal : 0.0;
        $refundAmount = ($refundType !== SalesReturnRefundType::CREDIT_NOTE->value) ? $grandTotal : 0.0;

        return [
            'subtotal' => round($subtotal, 4),
            'discount_amount' => round($totalDiscount, 4),
            'tax_amount' => round($taxAmount, 4),
            'rounding_amount' => round($roundingAmount, 4),
            'grand_total' => round($grandTotal, 4),
            'credited_amount' => round($creditedAmount, 4),
            'refund_amount' => round($refundAmount, 4),
        ];
    }
}
