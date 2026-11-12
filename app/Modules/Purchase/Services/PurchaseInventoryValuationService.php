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

            if ($charge->treatment === 'inventory') {
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
        $remainingAmount = $stockAmount;

        if ($stockItems !== []) {
            $totalWeight = $stockSubtotal;

            if (bccomp($totalWeight, '0.0000', 4) === 0) {
                $totalWeight = '0.0000';

                foreach ($stockItems as $item) {
                    $totalWeight = bcadd(
                        $totalWeight,
                        $this->decimal($item->base_quantity),
                        4
                    );
                }
            }

            if (bccomp($totalWeight, '0.0000', 4) <= 0) {
                throw new BusinessException(
                    'Stock purchase items require a positive valuation weight.'
                );
            }

            foreach ($stockItems as $index => $item) {
                $weight = bccomp($stockSubtotal, '0.0000', 4) > 0
                    ? $this->decimal($item->subtotal ?? '0')
                    : $this->decimal($item->base_quantity);

                $lineAmount = $index === array_key_last($stockItems)
                    ? $remainingAmount
                    : $this->decimal(
                        bcmul(
                            $stockAmount,
                            bcdiv($weight, $totalWeight, 8),
                            8
                        )
                    );

                $remainingAmount = bcsub(
                    $remainingAmount,
                    $lineAmount,
                    4
                );

                $baseQuantity = $this->decimal(
                    $item->base_quantity
                );

                if (bccomp($baseQuantity, '0.0000', 4) <= 0) {
                    throw new BusinessException(
                        'Stock purchase items require a positive base quantity.'
                    );
                }

                $unitCosts[$item->id] = $this->decimalCost(
                    bcdiv($lineAmount, $baseQuantity, 8)
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
