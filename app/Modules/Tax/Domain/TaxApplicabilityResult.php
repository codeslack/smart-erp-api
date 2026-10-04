<?php

namespace App\Modules\Tax\Domain;

final class TaxApplicabilityResult
{
    public function __construct(
        public readonly int $taxApplicabilityRuleId,
        public readonly int $taxGroupId,
        public readonly string $usage,
        public readonly string $direction,
        public readonly string $treatment,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tax_applicability_rule_id' => $this->taxApplicabilityRuleId,
            'tax_group_id' => $this->taxGroupId,
            'usage' => $this->usage,
            'direction' => $this->direction,
            'treatment' => $this->treatment,
        ];
    }
}