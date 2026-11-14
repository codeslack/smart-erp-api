<?php

namespace App\Modules\Purchase\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\Settings\Models\Setting;

class PurchaseInventoryValuationService
{
    public function calculate(Purchase $purchase): array
    {
        $purchase->loadMissing([
            'items.product',
            'charges.chartOfAccount',
        ]);

        $baseCurrency = strtoupper((string) (
            Setting::query()
                ->where('group', Setting::GROUP_COMPANY)
                ->where('key', 'currency')
                ->value('value') ?? 'INR'
        ));

        $currencyCode = strtoupper((string) (
            $purchase->currency_code ?: $baseCurrency
        ));

        $exchangeRate = (string) (
            $purchase->exchange_rate ?? '1.00000000'
        );

        if (
            ! preg_match('/^[A-Z]{3}$/', $currencyCode)
            || $baseCurrency === ''
            || ! is_numeric($exchangeRate)
            || (float) $exchangeRate <= 0
        ) {
            throw new BusinessException(
                'Purchase currency and exchange rate are invalid.'
            );
        }

        if (
            $currencyCode === $baseCurrency
            && bccomp($exchangeRate, '1.00000000', 8) !== 0
        ) {
            throw new BusinessException(
                'Purchase exchange rate must be 1 when purchase currency matches company base currency.'
            );
        }

        $grandTotal = $this->decimal($purchase->grand_total);
        $taxAmount = $this->decimal($purchase->tax_amount ?? '0');
        $discountAmount = $this->decimal($purchase->discount_amount ?? '0');
        $roundingAmount = $this->decimal($purchase->rounding_amount ?? '0');

        $stockItems = [];
        $stockSubtotal = '0.0000';
        $nonStockSubtotal = '0.0000';
        $lineDiscountTotal = '0.0000';

        foreach ($purchase->items as $item) {
            $subtotal = $this->decimal($item->subtotal ?? '0');
            $lineDiscountTotal = bcadd(
                $lineDiscountTotal,
                $this->decimal($item->discount_amount ?? '0'),
                4
            );

            if ($item->product?->requiresStock()) {
                $stockItems[] = $item;
                $stockSubtotal = bcadd($stockSubtotal, $subtotal, 4);
            } else {
                $nonStockSubtotal = bcadd($nonStockSubtotal, $subtotal, 4);
            }
        }

        $totalSubtotal = bcadd(
            $stockSubtotal,
            $nonStockSubtotal,
            4
        );

        $headerDiscount = bcsub(
            $discountAmount,
            $lineDiscountTotal,
            4
        );

        if (bccomp($headerDiscount, '0.0000', 4) < 0) {
            $headerDiscount = '0.0000';
        }

        if (bccomp($headerDiscount, $totalSubtotal, 4) > 0) {
            throw new BusinessException(
                'Purchase discount cannot exceed the item subtotal.'
            );
        }

        $stockDiscount = '0.0000';

        if (
            bccomp($totalSubtotal, '0.0000', 4) > 0
            && bccomp($stockSubtotal, '0.0000', 4) > 0
        ) {
            $stockDiscount = $this->decimal(
                bcmul(
                    $headerDiscount,
                    bcdiv($stockSubtotal, $totalSubtotal, 8),
                    8
                )
            );
        }

        $nonStockDiscount = bcsub(
            $headerDiscount,
            $stockDiscount,
            4
        );

        $inventoryCharges = '0.0000';
        $expenseCharges = '0.0000';

        foreach ($purchase->charges as $charge) {
            $amount = $this->decimal($charge->amount);

            if ($charge->isLandedCost()) {
                $inventoryCharges = bcadd(
                    $inventoryCharges,
                    $amount,
                    4
                );
            } elseif ($charge->treatment === 'expense') {
                $expenseCharges = bcadd(
                    $expenseCharges,
                    $amount,
                    4
                );
            }
        }

        $stockAmount = bcsub(
            bcadd($stockSubtotal, $inventoryCharges, 4),
            $stockDiscount,
            4
        );

        $nonStockAmount = bcsub(
            $nonStockSubtotal,
            $nonStockDiscount,
            4
        );

        if ($stockItems === []) {
            $nonStockAmount = bcadd(
                $nonStockAmount,
                $inventoryCharges,
                4
            );

            $stockAmount = '0.0000';
        }

        if ($stockItems !== []) {
            $stockAmount = bcadd(
                $stockAmount,
                $roundingAmount,
                4
            );
        } else {
            $nonStockAmount = bcadd(
                $nonStockAmount,
                $roundingAmount,
                4
            );
        }

        if (
            bccomp($taxAmount, $grandTotal, 4) > 0
            || bccomp($stockAmount, '0.0000', 4) < 0
            || bccomp($nonStockAmount, '0.0000', 4) < 0
        ) {
            throw new BusinessException(
                'Purchase totals, discounts, charges and tax do not form a valid valuation.'
            );
        }

        $calculatedTotal = bcadd(
            bcadd($stockAmount, $nonStockAmount, 4),
            bcadd($expenseCharges, $taxAmount, 4),
            4
        );

        $difference = bcsub(
            $grandTotal,
            $calculatedTotal,
            4
        );

        if (bccomp($difference, '0.0000', 4) !== 0) {
            if ($stockItems !== []) {
                $stockAmount = bcadd($stockAmount, $difference, 4);
            } else {
                $nonStockAmount = bcadd($nonStockAmount, $difference, 4);
            }
        }

        $unitCosts = [];

        if ($stockItems !== []) {
            $lineAmounts = [];

            foreach ($stockItems as $item) {
                $lineAmounts[$item->id] = $this->decimal(
                    $item->subtotal ?? '0'
                );
            }

            // Allocate the stock share of header discount by item value.
            $discountAllocations = $this->allocateAcrossItems(
                $stockItems,
                $stockDiscount,
                'by_value'
            );

            foreach ($discountAllocations as $itemId => $allocation) {
                $lineAmounts[$itemId] = bcsub(
                    $lineAmounts[$itemId],
                    $allocation,
                    4
                );
            }

            // Each landed-cost charge uses its configured allocation method.
            foreach ($purchase->charges as $charge) {
                if (! $charge->isLandedCost()) {
                    continue;
                }

                $chargeAllocations = $this->allocateAcrossItems(
                    $stockItems,
                    $this->decimal($charge->amount),
                    $charge->allocation_method ?? 'by_value'
                );

                foreach ($chargeAllocations as $itemId => $allocation) {
                    $lineAmounts[$itemId] = bcadd(
                        $lineAmounts[$itemId],
                        $allocation,
                        4
                    );
                }
            }

            // Document rounding is allocated by value; the final line absorbs
            // any residual so item costs reconcile exactly to the stock total.
            $roundingAllocations = $this->allocateAcrossItems(
                $stockItems,
                $roundingAmount,
                'by_value'
            );

            foreach ($roundingAllocations as $itemId => $allocation) {
                $lineAmounts[$itemId] = bcadd(
                    $lineAmounts[$itemId],
                    $allocation,
                    4
                );
            }

            $allocatedTotal = '0.0000';

            foreach ($lineAmounts as $lineAmount) {
                $allocatedTotal = bcadd(
                    $allocatedTotal,
                    $lineAmount,
                    4
                );
            }

            $lastItemId = $stockItems[array_key_last($stockItems)]->id;
            $difference = bcsub($stockAmount, $allocatedTotal, 4);

            if (bccomp($difference, '0.0000', 4) !== 0) {
                $lineAmounts[$lastItemId] = bcadd(
                    $lineAmounts[$lastItemId],
                    $difference,
                    4
                );
            }

            foreach ($stockItems as $item) {
                $baseQuantity = $this->decimal(
                    $item->base_quantity
                );

                if (bccomp($baseQuantity, '0.0000', 4) <= 0) {
                    throw new BusinessException(
                        'Stock purchase items require a positive base quantity.'
                    );
                }

                if (bccomp($lineAmounts[$item->id], '0.0000', 4) < 0) {
                    throw new BusinessException(
                        'Purchase discounts and landed costs cannot produce a negative stock item valuation.'
                    );
                }

                $unitCosts[$item->id] = $this->decimalCost(
                    bcdiv($lineAmounts[$item->id], $baseQuantity, 8)
                );
            }
        }

        return [
            'base_currency' => $baseCurrency,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'grand_total' => $grandTotal,
            'tax_amount' => $taxAmount,
            'stock_amount' => $stockAmount,
            'non_stock_amount' => $nonStockAmount,
            'expense_charges' => $expenseCharges,
            'stock_item_unit_costs' => $unitCosts,
        ];
    }

    /**
     * Allocate a landed-cost or discount amount across stock items.
     *
     * The final item receives the remaining four-decimal amount so the
     * allocation always reconciles to the source charge exactly.
     */
    protected function allocateAcrossItems(
        array $items,
        string $amount,
        string $method
    ): array {
        if ($items === []) {
            return [];
        }

        $weights = [];
        $totalWeight = '0.0000';

        foreach ($items as $item) {
            $weight = match ($method) {
                'by_value' => $this->decimal($item->subtotal ?? '0'),
                'by_quantity' => $this->decimal($item->base_quantity ?? '0'),
                'equal' => '1.0000',
                default => throw new BusinessException(
                    'Unsupported landed-cost allocation method.'
                ),
            };

            $weights[$item->id] = $weight;
            $totalWeight = bcadd($totalWeight, $weight, 4);
        }

        // When every stock line has zero value, value-based allocation falls
        // back to base quantity rather than dividing by zero.
        if (
            $method === 'by_value'
            && bccomp($totalWeight, '0.0000', 4) <= 0
        ) {
            $totalWeight = '0.0000';

            foreach ($items as $item) {
                $weights[$item->id] = $this->decimal(
                    $item->base_quantity ?? '0'
                );

                $totalWeight = bcadd(
                    $totalWeight,
                    $weights[$item->id],
                    4
                );
            }
        }

        if (bccomp($totalWeight, '0.0000', 4) <= 0) {
            throw new BusinessException(
                'Stock purchase items require a positive allocation weight.'
            );
        }

        $allocations = [];
        $remaining = $this->decimal($amount);
        $lastIndex = array_key_last($items);

        foreach ($items as $index => $item) {
            $allocation = $index === $lastIndex
                ? $remaining
                : $this->decimal(
                    bcmul(
                        $this->decimal($amount),
                        bcdiv($weights[$item->id], $totalWeight, 8),
                        8
                    )
                );

            $allocations[$item->id] = $allocation;
            $remaining = bcsub($remaining, $allocation, 4);
        }

        return $allocations;
    }

    protected function decimal(mixed $value): string
    {
        return number_format(
            (float) $value,
            4,
            '.',
            ''
        );
    }

    protected function decimalCost(mixed $value): string
    {
        return number_format(
            (float) $value,
            8,
            '.',
            ''
        );
    }
}
