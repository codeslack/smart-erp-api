<?php

namespace App\Modules\Purchase\Services\Calculators;

use App\Core\Exceptions\BusinessException;

class PurchaseCalculator
{
    /**
     * Allocate header discount across taxable item bases and recalculate
     * each item's tax. Line totals and item discounts remain pre-header
     * values so the document totals can retain an auditable breakdown.
     */
    public function applyHeaderDiscount(
        array $calculatedItems,
        array $headerData = []
    ): array {
        $headerDiscountAmount = $this->headerDiscountAmount(
            $calculatedItems,
            $headerData
        );

        if ($headerDiscountAmount <= 0) {
            return $calculatedItems;
        }

        $itemsSubtotal = array_sum(array_map(
            fn ($item) => (float) ($item['line_total'] ?? 0),
            $calculatedItems
        ));

        $itemDiscountTotal = array_sum(array_map(
            fn ($item) => (float) ($item['discount_amount'] ?? 0),
            $calculatedItems
        ));

        $baseForHeaderDiscount = max(
            0,
            $itemsSubtotal - $itemDiscountTotal
        );

        if ($baseForHeaderDiscount <= 0) {
            return $calculatedItems;
        }

        foreach ($calculatedItems as &$item) {
            $baseAfterItemDiscount = max(
                0,
                (float) ($item['line_total'] ?? 0)
                    - (float) ($item['discount_amount'] ?? 0)
            );

            if ($baseAfterItemDiscount <= 0) {
                $item['subtotal'] = 0;
                $item['tax_amount'] = 0;
                $item['total_amount'] = 0;
                continue;
            }

            $discountShare = round(
                $headerDiscountAmount
                    * ($baseAfterItemDiscount / $baseForHeaderDiscount),
                8
            );

            $taxableAmount = max(
                0,
                round($baseAfterItemDiscount - $discountShare, 4)
            );

            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $existingTaxAmount = (float) ($item['tax_amount'] ?? 0);

            $taxAmount = $taxRate > 0
                ? round($taxableAmount * ($taxRate / 100), 4)
                : $existingTaxAmount;

            $item['subtotal'] = $taxableAmount;
            $item['tax_amount'] = $taxAmount;
            $item['total_amount'] = round(
                $taxableAmount + $taxAmount,
                4
            );
        }
        unset($item);

        return $calculatedItems;
    }

    public function calculateTotals(
        array $calculatedItems,
        array $headerData = [],
        array $calculatedCharges = []
    ): array {
        $calculatedItems = $this->applyHeaderDiscount(
            $calculatedItems,
            $headerData
        );

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
        $headerDiscountAmount = $this->headerDiscountAmount(
            $calculatedItems,
            $headerData
        );

        $totalDiscountAmount = round(
            $itemDiscountTotal + $headerDiscountAmount,
            4
        );

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
            $itemsSubtotal
                - $totalDiscountAmount
                + $chargesSubtotal
                + $totalTaxAmount
                + $roundingAmount,
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

    protected function headerDiscountAmount(
        array $calculatedItems,
        array $headerData
    ): float {
        $itemsSubtotal = array_sum(array_map(
            fn ($item) => (float) ($item['line_total'] ?? 0),
            $calculatedItems
        ));

        $itemDiscountTotal = array_sum(array_map(
            fn ($item) => (float) ($item['discount_amount'] ?? 0),
            $calculatedItems
        ));

        $baseForHeaderDiscount = max(
            0,
            $itemsSubtotal - $itemDiscountTotal
        );

        $discountType = $headerData['discount_type'] ?? 'none';
        $discountRate = (float) ($headerData['discount_rate'] ?? 0);
        $discountAmount = (float) ($headerData['discount_amount'] ?? 0);

        if (
            $discountType === 'percentage'
            && ($discountRate < 0 || $discountRate > 100)
        ) {
            throw new BusinessException(
                'Header discount percentage must be between 0 and 100.'
            );
        }

        if (
            $discountType === 'percentage'
            && $discountAmount === 0.0
            && $discountRate > 0
        ) {
            $discountAmount = round(
                $baseForHeaderDiscount * ($discountRate / 100),
                4
            );
        } elseif (
            $discountType === 'fixed'
            && $discountAmount === 0.0
            && $discountRate > 0
        ) {
            $discountAmount = round($discountRate, 4);
        }

        if ($discountAmount > $baseForHeaderDiscount + 0.0001) {
            throw new BusinessException(
                'Header discount cannot exceed the net item subtotal.'
            );
        }

        return max(0, round($discountAmount, 4));
    }
}
