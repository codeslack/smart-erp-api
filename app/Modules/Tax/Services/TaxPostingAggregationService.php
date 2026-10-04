<?php

namespace App\Modules\Tax\Services;

use App\Modules\Tax\Domain\TaxPostingLine;

class TaxPostingAggregationService
{
    /**
     * Aggregate posting lines from multiple invoice lines.
     *
     * Lines are consolidated only when their:
     * - tax component
     * - accounting account
     * - direction
     *
     * are identical.
     *
     * @param TaxPostingLine[] $lines
     * @return TaxPostingLine[]
     */
    public function aggregate(array $lines): array
    {
        $aggregated = [];

        foreach ($lines as $line) {
            $key = $this->key($line);

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = new TaxPostingLine(
                    taxComponentId: $line->taxComponentId,
                    accountId: $line->accountId,
                    direction: $line->direction,
                    amount: $line->amount,
                );

                continue;
            }

            $existing = $aggregated[$key];

            $aggregated[$key] = new TaxPostingLine(
                taxComponentId: $existing->taxComponentId,
                accountId: $existing->accountId,
                direction: $existing->direction,
                amount: $existing->amount + $line->amount,
            );
        }

        return array_values($aggregated);
    }

    protected function key(TaxPostingLine $line): string
    {
        return implode('|', [
            $line->taxComponentId,
            $line->accountId,
            $line->direction,
        ]);
    }
}