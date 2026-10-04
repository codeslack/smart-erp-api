<?php

namespace App\Modules\Tax\Services;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Domain\TaxCalculationResult;
use App\Modules\Tax\Domain\TaxPostingLine;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;

class TaxPostingService
{
    public function __construct(
        protected TaxAccountingMappingRepositoryInterface $mappings,
    ) {}

    /**
     * Build accounting posting lines for one tax calculation.
     *
     * A purchase/sales document can call this method once for
     * each taxable line or tax calculation.
     *
     * @return TaxPostingLine[]
     */
    public function buildLines(
        TaxCalculationResult $calculation,
        TaxDirectionEnum $direction,
        string $calculationDate,
    ): array {
        $lines = [];

        foreach ($calculation->components as $component) {
            $taxAmount = $this->componentTaxAmount($component);

            if ($taxAmount <= 0) {
                continue;
            }

            $taxComponentId = $this->componentTaxComponentId(
                $component,
            );

            $mapping = $this->mappings->findForPosting(
                $taxComponentId,
                $direction->value,
                $calculationDate,
            );

            if ($mapping === null) {
                throw new BusinessException(
                    'Tax accounting mapping not found for component.'
                );
            }

            $lines[] = new TaxPostingLine(
                taxComponentId: $taxComponentId,
                accountId: $mapping->account_id,
                direction: $direction->value,
                amount: $taxAmount,
            );
        }

        return $lines;
    }

    protected function componentTaxComponentId(
        mixed $component,
    ): int {
        if (is_array($component)) {
            return (int) $component['tax_component_id'];
        }

        return (int) $component->taxComponentId;
    }

    protected function componentTaxAmount(
        mixed $component,
    ): float {
        if (is_array($component)) {
            return (float) ($component['tax_amount'] ?? 0);
        }

        return (float) $component->taxAmount;
    }
}