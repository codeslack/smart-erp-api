<?php

namespace App\Modules\SalesOrder\Services\Calculators;

class SalesOrderItemCalculator
{
    /**
     * Calculate item prices, discounts, taxes, and net totals.
     */
    public function calculate(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);

        $discountType = $item['discount_type'] ?? 'fixed';
        $discountRate = (float) ($item['discount_rate'] ?? 0);
        $discountAmountInput = (float) ($item['discount_amount'] ?? 0);

        $grossAmount = round($quantity * $unitPrice, 4);

        $discountAmount = $this->calculateDiscount(
            $grossAmount,
            $discountType,
            $discountRate,
            $discountAmountInput
        );

        $netAmount = max(0, round($grossAmount - $discountAmount, 4));
        $netUnitPrice = $quantity > 0 ? round($netAmount / $quantity, 4) : 0;

        $isTaxInclusive = (bool) ($item['is_tax_inclusive'] ?? false);
        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $taxTotalAmountInput = (float) ($item['tax_total_amount'] ?? $item['tax_amount'] ?? 0);

        if ($taxRate > 0) {
            if ($isTaxInclusive) {
                $taxTotalAmount = round($netAmount - ($netAmount / (1 + ($taxRate / 100))), 4);
                $taxBaseAmount = round($netAmount - $taxTotalAmount, 4);
                $totalAmount = $netAmount;
            } else {
                $taxBaseAmount = $netAmount;
                $taxTotalAmount = round($taxBaseAmount * ($taxRate / 100), 4);
                $totalAmount = round($taxBaseAmount + $taxTotalAmount, 4);
            }
        } else {
            $taxTotalAmount = $taxTotalAmountInput;
            $taxBaseAmount = $netAmount;
            $totalAmount = round($taxBaseAmount + $taxTotalAmount, 4);
        }

        return array_merge($item, [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount_amount' => round($discountAmount, 4),
            'net_unit_price' => round($netUnitPrice, 4),
            'subtotal_amount' => round($netAmount, 4),
            'tax_rate' => round($taxRate, 4),
            'is_tax_inclusive' => $isTaxInclusive,
            'tax_base_amount' => round($taxBaseAmount, 4),
            'tax_total_amount' => round($taxTotalAmount, 4),
            'total_amount' => round($totalAmount, 4),
        ]);
    }

    private function calculateDiscount(
        float $grossAmount,
        string $discountType,
        float $discountRate,
        float $discountAmountInput
    ): float {
        if ($discountType === 'percentage') {
            if ($discountRate <= 0) {
                return 0;
            }

            return round($grossAmount * ($discountRate / 100), 4);
        }

        $fixed = $discountAmountInput > 0 ? $discountAmountInput : $discountRate;

        return min($grossAmount, max(0, $fixed));
    }
}
