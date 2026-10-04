<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tax\Domain\TaxGroupRules;
use App\Modules\Tax\Models\TaxGroup;

use App\Modules\Tax\Repositories\Contracts\TaxGroupRepositoryInterface;

class TaxGroupService
{
    public function __construct(
        protected TaxGroupRepositoryInterface $repository,
        protected TaxGroupRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxGroup {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): TaxGroup
    {
        return DB::transaction(function () use ($data) {
            $effectiveFrom = $data['effective_from'];
            $effectiveTo = $data['effective_to'] ?? null;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validateUsage(
                $data['usage']
            );

            $this->rules->validateDirection(
                $data['direction']
            );

            $this->rules->validateTreatment(
                $data['treatment']
            );

            return $this->repository->create([
                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::TAX_GROUP
                ),

                'tax_id' =>
                    $data['tax_id'],

                'name' =>
                    $data['name'],

                'usage' =>
                    $data['usage'],

                'direction' =>
                    $data['direction'],

                'treatment' =>
                    $data['treatment'],

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
        TaxGroup $group,
        array $data
    ): TaxGroup {
        return DB::transaction(function () use (
            $group,
            $data
        ) {
            $effectiveFrom = $data['effective_from']
                ?? $group->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $group->effective_to?->toDateString();

            $taxId = $data['tax_id']
                ?? $group->tax_id;

            $name = $data['name']
                ?? $group->name;

            $usage = $data['usage']
                ?? $group->usage;

            $direction = $data['direction']
                ?? $group->direction;

            $treatment = $data['treatment']
                ?? $group->treatment;

            $isActive = $data['is_active']
                ?? $group->is_active;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validateUsage(
                $usage
            );

            $this->rules->validateDirection(
                $direction
            );

            $this->rules->validateTreatment(
                $treatment
            );

            $this->repository->update($group, [
                'tax_id' =>
                    $taxId,

                'name' =>
                    $name,

                'usage' =>
                    $usage,

                'direction' =>
                    $direction,

                'treatment' =>
                    $treatment,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);

            return $group->refresh();
        });
    }

    public function delete(
        TaxGroup $group
    ): bool {
        return DB::transaction(function () use ($group) {
            if ($group->components()->exists()) {
                throw new BusinessException(
                    'This tax group cannot be deleted because it has one or more tax components.'
                );
            }

            if ($group->applicabilityRules()->exists()) {
                throw new BusinessException(
                    'This tax group cannot be deleted because it has one or more applicability rules.'
                );
            }

            return $this->repository->delete($group);
        });
    }
}