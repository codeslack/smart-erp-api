<?php

namespace App\Modules\Sales\Services\Calculators;

class SaleItemCalculator
{
    public function calculate(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);

        $discountType = $item['discount_type'] ?? 'fixed';
        $discountRate = (float) ($item['discount_rate'] ?? 0);
        $discountAmountInput = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);

        $grossAmount = round($quantity * $unitPrice, 4);

        $discountAmount = $this->calculateDiscount(
            $grossAmount,
            $discountType,
            $discountRate,
            $discountAmountInput
        );

        $netAmount = max(0, round($grossAmount - $discountAmount, 4));
        $netUnitPrice = $quantity > 0 ? round($netAmount / $quantity, 4) : 0;

        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $taxAmountInput = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);

        if ($taxRate > 0) {
            $taxAmount = round($netAmount * ($taxRate / 100), 4);
        } else {
            $taxAmount = $taxAmountInput;
        }

        $lineTotal = round($netAmount + $taxAmount, 4);

        return array_merge($item, [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => 0,
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount_amount' => round($discountAmount, 4),
            'net_unit_price' => round($netUnitPrice, 4),
            'subtotal' => round($netAmount, 4),
            'tax_rate' => round($taxRate, 4),
            'tax_amount' => round($taxAmount, 4),
            'line_total' => round($lineTotal, 4),
            'total_amount' => round($lineTotal, 4),
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
