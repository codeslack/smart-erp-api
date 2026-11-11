<?php

namespace App\Modules\Sales\Services\Tax;

use App\Modules\Sales\Models\Sale;
use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;
use App\Modules\Tax\Models\TaxApplicabilityRule;
use App\Modules\Tax\Services\TaxCalculationService;

class SaleTaxCalculationService
{
    public function __construct(
        protected SaleTaxApplicabilityResolver $applicabilityResolver,
        protected TaxCalculationService $calculationService,
    ) {}

    public function calculateAndSnapshot(Sale $sale): void
    {
        $sale->loadMissing('customer', 'items');

        // Tax is optional until the tenant configures at least one applicability rule.
        $hasRules = TaxApplicabilityRule::query()->exists();

        if (! $hasRules) {
            if (bccomp((string) $sale->tax_amount, '0.0000', 4) > 0) {
                throw new \App\Core\Exceptions\BusinessException(
                    'Configure Tax V4 applicability rules before confirming a Sale with tax.'
                );
            }

            return;
        }

        $applicability = $this->applicabilityResolver->resolve($sale);
        $itemDiscounts = (float) $sale->items->sum('discount_amount');
        $headerDiscount = max(0, (float) $sale->discount_amount - $itemDiscounts);
        $subtotal = (float) $sale->items->sum('subtotal');
        $totalTax = 0.0;

        foreach ($sale->items as $item) {
            $headerDiscountShare = $subtotal > 0
                ? round($headerDiscount * ((float) $item->subtotal / $subtotal), 4)
                : 0.0;

            $taxableAmount = max(0, round((float) $item->subtotal - $headerDiscountShare, 4));

            $calculation = $this->calculationService->calculate(
                new TaxCalculationInput(
                    taxGroupId: $applicability->taxGroupId,
                    taxableAmount: $taxableAmount,
                    pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                    roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                    calculationDate: $sale->sale_date->toDateString(),
                ),
                4,
            );

            $lineTax = round(array_sum(array_map(
                fn (array $component) => (float) ($component['tax_amount'] ?? 0),
                $calculation->components,
            )), 4);

            $taxRate = $taxableAmount > 0
                ? round(($lineTax / $taxableAmount) * 100, 4)
                : 0.0;

            $snapshot = [
                'tax_group_id' => $applicability->taxGroupId,
                'tax_applicability_rule_id' => $applicability->taxApplicabilityRuleId,
                'usage' => $applicability->usage,
                'direction' => $applicability->direction,
                'treatment' => $applicability->treatment,
                'calculation_date' => $sale->sale_date->toDateString(),
                'taxable_amount' => $calculation->taxableAmount,
                'tax_amount' => $lineTax,
                'components' => $calculation->components,
            ];

            $item->update([
                'tax_group_id' => $applicability->taxGroupId,
                'tax_applicability_rule_id' => $applicability->taxApplicabilityRuleId,
                'tax_snapshot' => $snapshot,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => round((float) $item->subtotal + $lineTax, 4),
                'total_amount' => round((float) $item->subtotal + $lineTax, 4),
            ]);

            $totalTax += $lineTax;
        }

        $grandTotal = max(0, round(
            (float) $sale->subtotal
            - $headerDiscount
            + $totalTax
            + (float) $sale->charges_subtotal
            + (float) $sale->charges_tax_amount
            + (float) $sale->rounding_amount,
            4,
        ));

        $paidAmount = (float) $sale->paid_amount;

        $sale->update([
            'tax_amount' => round($totalTax, 4),
            'tax' => round($totalTax, 4),
            'grand_total' => $grandTotal,
            'due_amount' => max(0, round($grandTotal - $paidAmount, 4)),
        ]);
    }
}
