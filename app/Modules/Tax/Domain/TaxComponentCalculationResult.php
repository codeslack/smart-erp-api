<?php

namespace App\Modules\Tax\Domain;

final class TaxComponentCalculationResult
{
    public function __construct(
        public readonly int $taxComponentId,
        public readonly string $componentType,
        public readonly float $rate,
        public readonly float $taxableAmount,
        public readonly float $taxAmount,
        public readonly string $calculationMethod,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tax_component_id' => $this->taxComponentId,
            'component_type' => $this->componentType,
            'rate' => $this->rate,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
            'calculation_method' => $this->calculationMethod,
        ];
    }
}