<?php

namespace App\Modules\Tax\Domain;

final class TaxTransactionCalculationResult
{
    public function __construct(
        public readonly TaxApplicabilityResult $applicability,
        public readonly TaxCalculationResult $calculation,
    ) {
    }

    public function toArray(): array
    {
        return [
            'applicability' => $this->applicability->toArray(),
            'calculation' => $this->calculation->toArray(),
        ];
    }
}