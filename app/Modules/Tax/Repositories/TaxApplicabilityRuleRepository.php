<?php

namespace App\Modules\Tax\Repositories;

use Illuminate\Database\Eloquent\Builder;

use App\Core\Repositories\BaseRepository;

use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Models\TaxApplicabilityRule;

use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;

class TaxApplicabilityRuleRepository
    extends BaseRepository
    implements TaxApplicabilityRuleRepositoryInterface
{
    public function __construct(TaxApplicabilityRule $model)
    {
        parent::__construct($model);
    }

    public function findApplicable(
        TaxApplicabilityInput $input,
    ): ?TaxApplicabilityRule {
        return $this->model
            ->newQuery()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $input->calculationDate)
            ->where(function ($query) use ($input) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $input->calculationDate,
                    );
            })
            ->where(function ($query) use ($input) {
                $this->addScopeMatch(
                    $query,
                    'source_country_code',
                    $input->sourceCountryCode,
                );

                $this->addScopeMatch(
                    $query,
                    'source_state_code',
                    $input->sourceStateCode,
                );

                $this->addScopeMatch(
                    $query,
                    'destination_country_code',
                    $input->destinationCountryCode,
                );

                $this->addScopeMatch(
                    $query,
                    'destination_state_code',
                    $input->destinationStateCode,
                );

                $this->addScopeMatch(
                    $query,
                    'place_of_supply_state_code',
                    $input->placeOfSupplyStateCode,
                );
            })
            ->where(function ($query) use ($input) {
                $this->addRegistrationMatch(
                    $query,
                    'source_registration_required',
                    $input->sourceRegistrationRequired,
                );

                $this->addRegistrationMatch(
                    $query,
                    'destination_registration_required',
                    $input->destinationRegistrationRequired,
                );
            })
            ->whereHas('taxGroup', function ($query) use ($input) {
                $query
                    ->where('is_active', true)
                    ->where('usage', $input->usage->value)
                    ->where('direction', $input->direction->value)
                    ->whereDate(
                        'effective_from',
                        '<=',
                        $input->calculationDate,
                    )
                    ->where(function ($query) use ($input) {
                        $query
                            ->whereNull('effective_to')
                            ->orWhereDate(
                                'effective_to',
                                '>=',
                                $input->calculationDate,
                            );
                    });
            })
            ->orderBy('priority')
            ->orderByDesc('id')
            ->first();
    }

    protected function addScopeMatch(
        Builder $query,
        string $column,
        ?string $value,
    ): void {
        $query->where(function ($query) use ($column, $value) {
            $query->whereNull($column);

            if ($value !== null) {
                $query->orWhere($column, $value);
            }
        });
    }

    protected function addRegistrationMatch(
        Builder $query,
        string $column,
        ?bool $value,
    ): void {
        $query->where(function ($query) use ($column, $value) {
            $query->whereNull($column);

            if ($value !== null) {
                $query->orWhere($column, $value);
            }
        });
    }

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
    ): bool {
        $query = $this->model
            ->newQuery()
            ->where('tax_group_id', $taxGroupId)
            ->where('priority', $priority);

        $this->addScopeOverlapCondition(
            $query,
            'source_country_code',
            $sourceCountryCode,
        );

        $this->addScopeOverlapCondition(
            $query,
            'source_state_code',
            $sourceStateCode,
        );

        $this->addScopeOverlapCondition(
            $query,
            'destination_country_code',
            $destinationCountryCode,
        );

        $this->addScopeOverlapCondition(
            $query,
            'destination_state_code',
            $destinationStateCode,
        );

        $this->addScopeOverlapCondition(
            $query,
            'place_of_supply_state_code',
            $placeOfSupplyStateCode,
        );

        $this->addScopeOverlapCondition(
            $query,
            'source_registration_required',
            $sourceRegistrationRequired,
        );

        $this->addScopeOverlapCondition(
            $query,
            'destination_registration_required',
            $destinationRegistrationRequired,
        );

        if ($ignoreId !== null) {
            $query->where(
                $this->model->getKeyName(),
                '!=',
                $ignoreId,
            );
        }

        $query->whereDate('effective_from', '<=', $effectiveTo ?? '9999-12-31')
            ->where(function ($query) use ($effectiveFrom) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom);
            });

        return $query->exists();
    }

    protected function addScopeOverlapCondition(
        Builder $query,
        string $column,
        mixed $value,
    ): void {
        $query->where(function ($query) use ($column, $value) {
            $query->whereNull($column);

            if ($value !== null) {
                $query->orWhere($column, $value);
            }
        });
    }
}
