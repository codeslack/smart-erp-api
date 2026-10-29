<?php

namespace App\Modules\PurchaseReturn\Services\Calculators;

use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;

class PurchaseReturnCalculator
{
    public function calculateItem(array $item): array
    {
        $quantity = (float) ($item['returned_quantity'] ?? 0);
        $unitCost = (float) ($item['unit_cost'] ?? 0);
        $subtotal = round($quantity * $unitCost, 4);

        $discountAmount = round((float) ($item['discount_amount'] ?? $item['discount'] ?? 0), 4);
        $taxable = max(0, $subtotal - $discountAmount);

        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $taxAmount = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
        if ($taxAmount === 0.0 && $taxRate > 0) {
            $taxAmount = round($taxable * ($taxRate / 100), 4);
        }

        $lineTotal = round($taxable + $taxAmount, 4);

        return array_merge($item, [
            'returned_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
        ]);
    }

    public function calculateTotals(array $calculatedItems, array $headerData = []): array
    {
        $subtotal = 0.0;
        $discountAmount = 0.0;
        $taxAmount = 0.0;

        foreach ($calculatedItems as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
            $discountAmount += (float) ($item['discount_amount'] ?? 0);
            $taxAmount += (float) ($item['tax_amount'] ?? 0);
        }

        $subtotal = round($subtotal, 4);
        $discountAmount = round($discountAmount, 4);
        $taxAmount = round($taxAmount, 4);
        $roundingAmount = round((float) ($headerData['rounding_amount'] ?? 0), 4);

        $grandTotal = max(0, round($subtotal - $discountAmount + $taxAmount + $roundingAmount, 4));

        $refundType = $headerData['refund_type'] ?? PurchaseReturnRefundType::CREDIT_NOTE->value;
        if ($refundType instanceof PurchaseReturnRefundType) {
            $refundType = $refundType->value;
        }

        $creditedAmount = ($refundType === PurchaseReturnRefundType::CREDIT_NOTE->value) ? $grandTotal : 0.0;
        $refundAmount = ($refundType !== PurchaseReturnRefundType::CREDIT_NOTE->value) ? $grandTotal : 0.0;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'rounding_amount' => $roundingAmount,
            'grand_total' => $grandTotal,
            'credited_amount' => $creditedAmount,
            'refund_amount' => $refundAmount,
        ];
    }
}
