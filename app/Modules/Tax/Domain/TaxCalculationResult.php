<?php

namespace App\Modules\Tax\Domain;

final class TaxCalculationResult
{
    public function __construct(
        public readonly float $taxableAmount,
        public readonly float $totalTax,
        public readonly float $totalAmount,
        public readonly array $components,
    ) {
    }

    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxableAmount,
            'total_tax' => $this->totalTax,
            'total_amount' => $this->totalAmount,
            'components' => $this->components,
        ];
    }
}