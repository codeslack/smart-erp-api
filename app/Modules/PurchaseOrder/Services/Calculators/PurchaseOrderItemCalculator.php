<?php

namespace App\Modules\PurchaseOrder\Services\Calculators;

class PurchaseOrderItemCalculator
{
    /**
     * Calculate all derived monetary values for a PO item.
     *
     * Tax calculation is intentionally kept at zero for now.
     * Tax V4 integration will be added at the service/domain boundary
     * once the Tax calculation API is finalized.
     */
    public function calculate(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);

        $discountType = $item['discount_type'] ?? 'fixed';
        $discountRate = (float) ($item['discount_rate'] ?? 0);

        $grossAmount = $quantity * $unitPrice;

        $discountAmount = $this->calculateDiscount(
            $grossAmount,
            $discountType,
            $discountRate
        );

        $netAmount = max(
            0,
            $grossAmount - $discountAmount
        );

        $netUnitPrice = $quantity > 0
            ? $netAmount / $quantity
            : 0;

        /*
         * Tax calculation will be supplied by Tax V4.
         * For now, tax is zero when the PO calculation is performed.
         */
        $taxTotalAmount = 0;

        $taxBaseAmount = $netAmount;

        $totalAmount = $taxBaseAmount + $taxTotalAmount;

        return array_merge($item, [
            'discount_amount' => round(
                $discountAmount,
                4
            ),

            'net_unit_price' => round(
                $netUnitPrice,
                4
            ),

            'subtotal_amount' => round(
                $netAmount,
                4
            ),

            'tax_base_amount' => round(
                $taxBaseAmount,
                4
            ),

            'tax_total_amount' => round(
                $taxTotalAmount,
                4
            ),

            'total_amount' => round(
                $totalAmount,
                4
            ),

            // Fulfillment counters are maintained only by receiving/billing workflows.
            'received_quantity' => 0,
            'billed_quantity' => 0,
            'cancelled_quantity' => 0,
        ]);
    }

    private function calculateDiscount(
        float $grossAmount,
        string $discountType,
        float $discountRate
    ): float {
        if ($discountRate <= 0) {
            return 0;
        }

        if ($discountType === 'percentage') {
            return $grossAmount
                * ($discountRate / 100);
        }

        return min(
            $discountRate,
            $grossAmount
        );
    }
}