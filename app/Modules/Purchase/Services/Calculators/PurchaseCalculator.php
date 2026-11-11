<?php

namespace App\Modules\Purchase\Services\Calculators;

use App\Core\Exceptions\BusinessException;

class PurchaseCalculator
{
    public function calculateTotals(
        array $calculatedItems,
        array $headerData = [],
        array $calculatedCharges = []
    ): array {
        $itemsSubtotal = 0.0;
        $itemDiscountTotal = 0.0;
        $itemTaxTotal = 0.0;

        foreach ($calculatedItems as $item) {
            $itemsSubtotal += (float) ($item['line_total'] ?? 0);
            $itemDiscountTotal += (float) ($item['discount_amount'] ?? 0);
            $itemTaxTotal += (float) ($item['tax_amount'] ?? 0);
        }

        $itemsSubtotal = round($itemsSubtotal, 4);
        $itemDiscountTotal = round($itemDiscountTotal, 4);
        $itemTaxTotal = round($itemTaxTotal, 4);

        $discountType = $headerData['discount_type'] ?? 'none';
        $discountRate = (float) ($headerData['discount_rate'] ?? 0);
        $headerDiscountAmount = (float) ($headerData['discount_amount'] ?? 0);
        $baseForHeaderDiscount = max(0, $itemsSubtotal - $itemDiscountTotal);

        if ($discountType === 'percentage' && $discountRate > 100) {
            throw new BusinessException('Header discount percentage must be between 0 and 100.');
        }

        if ($discountType === 'percentage' && $headerDiscountAmount === 0.0 && $discountRate > 0) {
            $headerDiscountAmount = round($baseForHeaderDiscount * ($discountRate / 100), 4);
        } elseif ($discountType === 'fixed' && $headerDiscountAmount === 0.0 && $discountRate > 0) {
            $headerDiscountAmount = round($discountRate, 4);
        }

        if ($headerDiscountAmount > $baseForHeaderDiscount + 0.0001) {
            throw new BusinessException('Header discount cannot exceed the net item subtotal.');
        }

        $totalDiscountAmount = round($itemDiscountTotal + $headerDiscountAmount, 4);

        $chargesSubtotal = 0.0;
        $chargesTaxAmount = 0.0;

        foreach ($calculatedCharges as $charge) {
            $chargesSubtotal += (float) ($charge['amount'] ?? 0);
            $chargesTaxAmount += (float) ($charge['tax_amount'] ?? 0);
        }

        $chargesSubtotal = round($chargesSubtotal, 4);
        $chargesTaxAmount = round($chargesTaxAmount, 4);
        $totalTaxAmount = round($itemTaxTotal + $chargesTaxAmount, 4);

        $roundingAmount = (float) ($headerData['rounding_amount'] ?? 0);

        $grandTotal = round(
            $itemsSubtotal - $totalDiscountAmount + $chargesSubtotal + $totalTaxAmount + $roundingAmount,
            4
        );
        $grandTotal = max(0, $grandTotal);

        $paidAmount = (float) ($headerData['paid_amount'] ?? 0);
        $dueAmount = max(0, round($grandTotal - $paidAmount, 4));

        return [
            'subtotal' => $itemsSubtotal,
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount_amount' => $totalDiscountAmount,
            'charges_subtotal' => $chargesSubtotal,
            'charges_tax_amount' => $chargesTaxAmount,
            'tax_amount' => $totalTaxAmount,
            'rounding_amount' => $roundingAmount,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ];
    }
}
