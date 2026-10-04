<?php

namespace App\Modules\Tax\Domain;

readonly class TaxPostingLine
{
    public function __construct(
        public int $taxComponentId,
        public int $accountId,
        public string $direction,
        public float $amount,
    ) {
    }

    /**
     * @return array{
     *     tax_component_id:int,
     *     account_id:int,
     *     direction:string,
     *     amount:float
     * }
     */
    public function toArray(): array
    {
        return [
            'tax_component_id' => $this->taxComponentId,
            'account_id' => $this->accountId,
            'direction' => $this->direction,
            'amount' => $this->amount,
        ];
    }
}