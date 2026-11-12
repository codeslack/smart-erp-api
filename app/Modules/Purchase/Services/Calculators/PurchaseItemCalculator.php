<?php

namespace App\Modules\Purchase\Services\Calculators;

use App\Core\Exceptions\BusinessException;

class PurchaseItemCalculator
{
    public function calculate(
        array $data,
        string $taxCalculationMode = 'exclusive'
    ): array {
        $quantity = (float) ($data['quantity'] ?? 0);
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        $grossLineTotal = round($quantity * $unitCost, 4);

        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $taxAmountInput = (float) ($data['tax_amount'] ?? 0);
        $inclusive = strtolower($taxCalculationMode) === 'inclusive';

        /*
         * Unit cost is tax-inclusive only when the document explicitly
         * selects inclusive pricing. All persisted line totals remain
         * tax-exclusive so accounting and inventory use the same base.
         */
        $lineTotal = $grossLineTotal;

        if ($inclusive) {
            $lineTotal = $taxRate > 0
                ? round(
                    $grossLineTotal / (1 + ($taxRate / 100)),
                    4
                )
                : max(
                    0,
                    round($grossLineTotal - $taxAmountInput, 4)
                );
        }

        $discountType = $data['discount_type'] ?? 'none';
        $discountRate = (float) (
            $data['discount_rate']
            ?? $data['discount_percentage']
            ?? 0
        );
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if (
            ($discountType === 'percentage'
                || ($discountType === 'none' && $discountRate > 0))
            && ($discountRate < 0 || $discountRate > 100)
        ) {
            throw new BusinessException(
                'Percentage discounts must be between 0 and 100.'
            );
        }

        if ($discountAmount > $lineTotal + 0.0001) {
            throw new BusinessException(
                'Item discount cannot exceed the tax-exclusive line total.'
            );
        }

        if (
            $discountType === 'percentage'
            || ($discountType === 'none' && $discountRate > 0)
        ) {
            $discountType = 'percentage';

            if ($discountAmount === 0.0 && $discountRate > 0) {
                $discountAmount = round(
                    $lineTotal * ($discountRate / 100),
                    4
                );
            }
        } elseif ($discountType === 'fixed') {
            if ($discountAmount === 0.0 && $discountRate > 0) {
                $discountAmount = round($discountRate, 4);
            }
        }

        if ($discountAmount > $lineTotal + 0.0001) {
            throw new BusinessException(
                'Item discount cannot exceed the tax-exclusive line total.'
            );
        }

        $subtotal = max(
            0,
            round($lineTotal - $discountAmount, 4)
        );

        $taxAmount = $taxRate > 0
            ? round($subtotal * ($taxRate / 100), 4)
            : (
                $lineTotal > 0
                    ? round(
                        $taxAmountInput * ($subtotal / $lineTotal),
                        4
                    )
                    : 0.0
            );

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
