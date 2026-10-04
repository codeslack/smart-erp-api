<?php

namespace App\Modules\Tax\Repositories\Contracts;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Models\TaxApplicabilityRule;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface TaxApplicabilityRuleRepositoryInterface extends BaseRepositoryInterface
{
    public function findApplicable(
        TaxApplicabilityInput $input,
    ): ?TaxApplicabilityRule;

    public function existsOverlappingPeriod(
        int $taxGroupId,
        int $priority,
        ?string $sourceCountryCode,
        ?string $sourceStateCode,
        ?string $destinationCountryCode,
        ?string $destinationStateCode,
        ?string $placeOfSupplyStateCode,
        ?bool $sourceRegistrationRequired,
        ?bool $destinationRegistrationRequired,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null,
    ): bool;
}