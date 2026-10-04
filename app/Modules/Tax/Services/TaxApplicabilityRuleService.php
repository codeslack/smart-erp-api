<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tax\Domain\TaxApplicabilityRuleRules;
use App\Modules\Tax\Models\TaxApplicabilityRule;

use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;

class TaxApplicabilityRuleService
{
    public function __construct(
        protected TaxApplicabilityRuleRepositoryInterface $rulesRepository,
        protected TaxApplicabilityRuleRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->rulesRepository->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxApplicabilityRule {
        return $this->rulesRepository->findByUuid($uuid);
    }

    public function create(
        array $data
    ): TaxApplicabilityRule {
        return DB::transaction(function () use ($data) {
            $effectiveFrom = $data['effective_from'];
            $effectiveTo = $data['effective_to'] ?? null;

            $sourceCountryCode =
                $data['source_country_code'] ?? null;

            $sourceStateCode =
                $data['source_state_code'] ?? null;

            $destinationCountryCode =
                $data['destination_country_code'] ?? null;

            $destinationStateCode =
                $data['destination_state_code'] ?? null;

            $placeOfSupplyStateCode =
                $data['place_of_supply_state_code'] ?? null;

            $priority = (int) (
                $data['priority'] ?? 1
            );

            $sourceRegistrationRequired =
                $data['source_registration_required'] ?? null;

            $destinationRegistrationRequired =
                $data['destination_registration_required'] ?? null;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validatePriority(
                $priority
            );

            $this->rules->validateLocationCodes(
                $sourceCountryCode,
                $sourceStateCode,
                $destinationCountryCode,
                $destinationStateCode,
                $placeOfSupplyStateCode
            );

            $this->rules->validateRegistrationRequirement(
                $sourceRegistrationRequired,
                $destinationRegistrationRequired
            );

            $this->validateOverlappingPeriod(
                (int) $data['tax_group_id'],
                $priority,
                $sourceCountryCode,
                $sourceStateCode,
                $destinationCountryCode,
                $destinationStateCode,
                $placeOfSupplyStateCode,
                $sourceRegistrationRequired,
                $destinationRegistrationRequired,
                $effectiveFrom,
                $effectiveTo
            );

            return $this->rulesRepository->create([
                'tax_group_id' =>
                    $data['tax_group_id'],

                'source_country_code' =>
                    $sourceCountryCode,

                'source_state_code' =>
                    $sourceStateCode,

                'destination_country_code' =>
                    $destinationCountryCode,

                'destination_state_code' =>
                    $destinationStateCode,

                'place_of_supply_state_code' =>
                    $placeOfSupplyStateCode,

                'source_registration_required' =>
                    $sourceRegistrationRequired,

                'destination_registration_required' =>
                    $destinationRegistrationRequired,

                'priority' =>
                    $priority,

                'is_active' =>
                    $data['is_active'] ?? true,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);
        });
    }

    public function update(
        TaxApplicabilityRule $rule,
        array $data
    ): TaxApplicabilityRule {
        return DB::transaction(function () use (
            $rule,
            $data
        ) {
            $taxGroupId = (int) (
                $data['tax_group_id']
                ?? $rule->tax_group_id
            );

            $effectiveFrom = $data['effective_from']
                ?? $rule->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $rule->effective_to?->toDateString();

            $priority = (int) (
                $data['priority']
                ?? $rule->priority
            );

            $sourceCountryCode =
                array_key_exists(
                    'source_country_code',
                    $data
                )
                    ? $data['source_country_code']
                    : $rule->source_country_code;

            $sourceStateCode =
                array_key_exists(
                    'source_state_code',
                    $data
                )
                    ? $data['source_state_code']
                    : $rule->source_state_code;

            $destinationCountryCode =
                array_key_exists(
                    'destination_country_code',
                    $data
                )
                    ? $data['destination_country_code']
                    : $rule->destination_country_code;

            $destinationStateCode =
                array_key_exists(
                    'destination_state_code',
                    $data
                )
                    ? $data['destination_state_code']
                    : $rule->destination_state_code;

            $placeOfSupplyStateCode =
                array_key_exists(
                    'place_of_supply_state_code',
                    $data
                )
                    ? $data['place_of_supply_state_code']
                    : $rule->place_of_supply_state_code;

            $sourceRegistrationRequired =
                array_key_exists(
                    'source_registration_required',
                    $data
                )
                    ? $data['source_registration_required']
                    : $rule->source_registration_required;

            $destinationRegistrationRequired =
                array_key_exists(
                    'destination_registration_required',
                    $data
                )
                    ? $data['destination_registration_required']
                    : $rule->destination_registration_required;

            $isActive = $data['is_active']
                ?? $rule->is_active;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validatePriority(
                $priority
            );

            $this->rules->validateLocationCodes(
                $sourceCountryCode,
                $sourceStateCode,
                $destinationCountryCode,
                $destinationStateCode,
                $placeOfSupplyStateCode
            );

            $this->rules->validateRegistrationRequirement(
                $sourceRegistrationRequired,
                $destinationRegistrationRequired
            );

            $this->validateOverlappingPeriod(
                $taxGroupId,
                $priority,
                $sourceCountryCode,
                $sourceStateCode,
                $destinationCountryCode,
                $destinationStateCode,
                $placeOfSupplyStateCode,
                $sourceRegistrationRequired,
                $destinationRegistrationRequired,
                $effectiveFrom,
                $effectiveTo,
                $rule->getKey()
            );

            $this->rulesRepository->update($rule, [
                'tax_group_id' =>
                    $taxGroupId,

                'source_country_code' =>
                    $sourceCountryCode,

                'source_state_code' =>
                    $sourceStateCode,

                'destination_country_code' =>
                    $destinationCountryCode,

                'destination_state_code' =>
                    $destinationStateCode,

                'place_of_supply_state_code' =>
                    $placeOfSupplyStateCode,

                'source_registration_required' =>
                    $sourceRegistrationRequired,

                'destination_registration_required' =>
                    $destinationRegistrationRequired,

                'priority' =>
                    $priority,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);

            return $rule->refresh();
        });
    }

    public function delete(
        TaxApplicabilityRule $rule
    ): bool {
        return DB::transaction(function () use ($rule) {
            return $this->rulesRepository->delete($rule);
        });
    }

    protected function validateOverlappingPeriod(
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
        ?int $ignoreId = null
    ): void {
        if (
            $this->rulesRepository->existsOverlappingPeriod(
                $taxGroupId,
                $priority,
                $sourceCountryCode,
                $sourceStateCode,
                $destinationCountryCode,
                $destinationStateCode,
                $placeOfSupplyStateCode,
                $sourceRegistrationRequired,
                $destinationRegistrationRequired,
                $effectiveFrom,
                $effectiveTo,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                'An overlapping tax applicability rule exists for the same scope and priority.'
            );
        }
    }
}