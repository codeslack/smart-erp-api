<?php

namespace App\Modules\Purchase\Services\Calculators;

class PurchaseItemCalculator
{
    public function calculate(array $data): array
    {
        $quantity = (float) ($data['quantity'] ?? 0);
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        $lineTotal = round($quantity * $unitCost, 4);

        $discountType = $data['discount_type'] ?? 'none';
        $discountRate = (float) ($data['discount_rate'] ?? $data['discount_percentage'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountType === 'percentage' || ($discountType === 'none' && $discountRate > 0)) {
            $discountType = 'percentage';
            if ($discountAmount === 0.0 && $discountRate > 0) {
                $discountAmount = round($lineTotal * ($discountRate / 100), 4);
            }
        } elseif ($discountType === 'fixed') {
            if ($discountAmount === 0.0 && $discountRate > 0) {
                $discountAmount = round($discountRate, 4);
            }
        }

        $subtotal = max(0, round($lineTotal - $discountAmount, 4));

        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $taxAmount = (float) ($data['tax_amount'] ?? 0);

        if ($taxRate > 0 && $taxAmount === 0.0) {
            $taxAmount = round($subtotal * ($taxRate / 100), 4);
        }

        $totalAmount = round($subtotal + $taxAmount, 4);

        return [
            ...$data,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $lineTotal,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount_percentage' => $discountRate,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
