<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;

final class TaxPricingCalculator
{
    /**
     * Resolve the taxable base from the amount supplied by the transaction.
     *
     * For EXCLUSIVE pricing, the supplied amount is already taxable.
     *
     * For INCLUSIVE pricing, the supplied amount contains percentage-based
     * taxes, so the taxable base is derived from the combined percentage.
     *
     * Fixed tax components are not supported for inclusive pricing because
     * their base cannot be derived from the gross amount without an explicit
     * allocation rule.
     */
    public function taxableAmount(
        float $amount,
        string $pricingMode,
        float $percentageRate,
        bool $hasFixedComponent = false,
    ): float {
        if ($amount < 0) {
            throw new BusinessException(
                'Taxable amount cannot be negative.',
            );
        }

        if ($pricingMode === 'EXCLUSIVE') {
            return $amount;
        }

        if ($pricingMode !== 'INCLUSIVE') {
            throw new BusinessException(
                'Unsupported tax pricing mode.',
            );
        }

        if ($hasFixedComponent) {
            throw new BusinessException(
                'Fixed tax components are not supported with inclusive pricing.',
            );
        }

        $multiplier = 1 + ($percentageRate / 100);

        if ($multiplier <= 0) {
            throw new BusinessException(
                'Invalid tax rate for inclusive pricing.',
            );
        }

        return $amount / $multiplier;
    }
}