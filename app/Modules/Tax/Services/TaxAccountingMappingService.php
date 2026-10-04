<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Domain\TaxAccountingMappingRules;
use App\Modules\Tax\Models\TaxAccountingMapping;

use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;

class TaxAccountingMappingService
{
    public function __construct(
        protected TaxAccountingMappingRepositoryInterface $mappings,
        protected TaxAccountingMappingRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->mappings->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxAccountingMapping {
        return $this->mappings->findByUuid($uuid);
    }

    public function create(
        array $data
    ): TaxAccountingMapping {
        return DB::transaction(function () use ($data) {
            $effectiveFrom = $data['effective_from'];
            $effectiveTo = $data['effective_to'] ?? null;

            $taxComponentId = (int) (
                $data['tax_component_id']
            );

            $accountId = (int) (
                $data['account_id']
            );

            $direction = $data['direction'];

            $isActive = $data['is_active'] ?? true;

            $this->rules->validateDirection(
                $direction
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->validateOverlappingPeriod(
                $taxComponentId,
                $direction,
                $effectiveFrom,
                $effectiveTo
            );

            return $this->mappings->create([
                'tax_component_id' =>
                    $taxComponentId,

                'account_id' =>
                    $accountId,

                'direction' =>
                    $direction,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);
        });
    }

    public function update(
        TaxAccountingMapping $mapping,
        array $data
    ): TaxAccountingMapping {
        return DB::transaction(function () use (
            $mapping,
            $data
        ) {
            $taxComponentId = (int) (
                $data['tax_component_id']
                ?? $mapping->tax_component_id
            );

            $accountId = (int) (
                $data['account_id']
                ?? $mapping->account_id
            );

            $direction = $data['direction']
                ?? $mapping->direction;

            $effectiveFrom = $data['effective_from']
                ?? $mapping->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $mapping->effective_to?->toDateString();

            $isActive = $data['is_active']
                ?? $mapping->is_active;

            $this->rules->validateDirection(
                $direction
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->validateOverlappingPeriod(
                $taxComponentId,
                $direction,
                $effectiveFrom,
                $effectiveTo,
                $mapping->getKey()
            );

            $this->mappings->update($mapping, [
                'tax_component_id' =>
                    $taxComponentId,

                'account_id' =>
                    $accountId,

                'direction' =>
                    $direction,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);

            return $mapping->refresh();
        });
    }

    public function delete(
        TaxAccountingMapping $mapping
    ): bool {
        return DB::transaction(function () use ($mapping) {
            return $this->mappings->delete($mapping);
        });
    }

    protected function validateOverlappingPeriod(
        int $taxComponentId,
        string $direction,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): void {
        if (
            $this->mappings->existsOverlappingPeriod(
                $taxComponentId,
                $direction,
                $effectiveFrom,
                $effectiveTo,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                'An overlapping tax accounting mapping period already exists.'
            );
        }
    }
}