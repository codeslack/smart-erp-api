<?php

namespace App\Modules\Tax\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Domain\TaxApplicabilityResult;

use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;

class TaxApplicabilityService
{
    public function __construct(
        protected TaxApplicabilityRuleRepositoryInterface $rules,
    ) {
    }

    public function resolve(
        TaxApplicabilityInput $input,
    ): TaxApplicabilityResult {
        $rule = $this->rules->findApplicable($input);

        if ($rule === null) {
            throw new BusinessException(
                'No applicable tax rule was found for the transaction context.',
            );
        }

        $taxGroup = $rule->taxGroup;

        if ($taxGroup === null) {
            throw new BusinessException(
                'The applicable tax rule references an invalid tax group.',
            );
        }

        return new TaxApplicabilityResult(
            taxApplicabilityRuleId: $rule->id,
            taxGroupId: $taxGroup->id,
            usage: $taxGroup->usage,
            direction: $taxGroup->direction,
            treatment: $taxGroup->treatment,
        );
    }
}