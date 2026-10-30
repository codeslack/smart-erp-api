<?php

namespace App\Modules\SalesQuotation\Services\Calculators;

class SalesQuotationCalculator
{
    public function __construct(
        protected SalesQuotationItemCalculator $itemCalculator
    ) {}

    public function calculate(array $data): array
    {
        $rawItems = $data['items'] ?? [];
        $calculatedItems = [];

        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;

        foreach ($rawItems as $item) {
            $calculated = $this->itemCalculator->calculate($item);
            $calculatedItems[] = $calculated;

            $subtotal += (float) ($calculated['subtotal'] ?? 0);
            $totalDiscount += (float) ($calculated['discount_amount'] ?? 0);
            $totalTax += (float) ($calculated['tax_amount'] ?? 0);
        }

        $shippingAmount = (float) ($data['shipping_amount'] ?? 0);
        $roundingAmount = (float) ($data['rounding_amount'] ?? 0);

        $grandTotal = max(0, round($subtotal + $totalTax + $shippingAmount + $roundingAmount, 4));

        $data['subtotal'] = round($subtotal, 4);
        $data['discount_amount'] = round($totalDiscount, 4);
        $data['tax_amount'] = round($totalTax, 4);
        $data['shipping_amount'] = round($shippingAmount, 4);
        $data['rounding_amount'] = round($roundingAmount, 4);
        $data['grand_total'] = round($grandTotal, 4);
        $data['items'] = $calculatedItems;

        return $data;
    }
}
