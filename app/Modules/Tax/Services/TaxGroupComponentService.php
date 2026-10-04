<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tax\Domain\TaxGroupComponentRules;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

use App\Modules\Tax\Models\TaxGroupComponent;

use App\Modules\Tax\Repositories\Contracts\TaxGroupComponentRepositoryInterface;

class TaxGroupComponentService
{
    public function __construct(
        protected TaxGroupComponentRepositoryInterface $components,
        protected TaxGroupComponentRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->components->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxGroupComponent {
        return $this->components->findByUuid($uuid);
    }

    public function create(array $data): TaxGroupComponent
    {
        return DB::transaction(function () use ($data) {
            $calculationMethod = $data['calculation_method']
                ?? TaxCalculationMethodEnum::PERCENTAGE->value;

            $effectiveFrom = $data['effective_from'];
            $effectiveTo = $data['effective_to'] ?? null;

            $this->rules->validateCalculationMethod(
                $calculationMethod
            );

            $this->rules->validateRate(
                (float) $data['rate'],
                $calculationMethod
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->validateOverlappingPeriod(
                (int) $data['tax_group_id'],
                (int) $data['tax_component_id'],
                $effectiveFrom,
                $effectiveTo
            );

            return $this->components->create([
                'tax_group_id' =>
                    $data['tax_group_id'],

                'tax_component_id' =>
                    $data['tax_component_id'],

                'rate' =>
                    $data['rate'],

                'calculation_method' =>
                    $calculationMethod,

                'is_active' =>
                    $data['is_active'] ?? true,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,

                'sort_order' =>
                    $data['sort_order'] ?? 1,
            ]);
        });
    }

    public function update(
        TaxGroupComponent $component,
        array $data
    ): TaxGroupComponent {
        return DB::transaction(function () use (
            $component,
            $data
        ) {
            $taxGroupId = (int) (
                $data['tax_group_id']
                ?? $component->tax_group_id
            );

            $taxComponentId = (int) (
                $data['tax_component_id']
                ?? $component->tax_component_id
            );

            $rate = $data['rate']
                ?? $component->rate;

            $calculationMethod = $data['calculation_method']
                ?? $component->calculation_method;

            $effectiveFrom = $data['effective_from']
                ?? $component->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $component->effective_to?->toDateString();

            $isActive = $data['is_active']
                ?? $component->is_active;

            $sortOrder = $data['sort_order']
                ?? $component->sort_order;

            $this->rules->validateCalculationMethod(
                $calculationMethod
            );

            $this->rules->validateRate(
                (float) $rate,
                $calculationMethod
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->validateOverlappingPeriod(
                $taxGroupId,
                $taxComponentId,
                $effectiveFrom,
                $effectiveTo,
                $component->getKey()
            );

            $this->components->update($component, [
                'tax_group_id' =>
                    $taxGroupId,

                'tax_component_id' =>
                    $taxComponentId,

                'rate' =>
                    $rate,

                'calculation_method' =>
                    $calculationMethod,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,

                'sort_order' =>
                    $sortOrder,
            ]);

            return $component->refresh();
        });
    }

    public function delete(
        TaxGroupComponent $component
    ): bool {
        return DB::transaction(function () use ($component) {
            return $this->components->delete($component);
        });
    }

    protected function validateOverlappingPeriod(
        int $taxGroupId,
        int $taxComponentId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): void {
        if (
            $this->components->existsOverlappingPeriod(
                $taxGroupId,
                $taxComponentId,
                $effectiveFrom,
                $effectiveTo,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                'An overlapping tax component period already exists.'
            );
        }
    }
}