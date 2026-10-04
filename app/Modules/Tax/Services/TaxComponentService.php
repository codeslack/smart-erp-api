<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tax\Domain\TaxComponentRules;
use App\Modules\Tax\Models\TaxComponent;

use App\Modules\Tax\Repositories\Contracts\TaxComponentRepositoryInterface;


class TaxComponentService
{
    public function __construct(
        protected TaxComponentRepositoryInterface $repository,
        protected TaxComponentRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxComponent {
        return $this->repository->findByUuid($uuid);
    }

    public function create(
        array $data
    ): TaxComponent {
        return DB::transaction(function () use ($data) {
            $componentType =
                $data['component_type'];

            $this->rules->validateComponentType(
                $componentType
            );

            return $this->repository->create([
                'code' =>
                    nextSystemNumber(
                        SystemNumberTypeEnum::TAX_COMPONENT
                    ),

                'name' =>
                    $data['name'],

                'component_type' =>
                    $componentType,

                'is_active' =>
                    $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(
        TaxComponent $component,
        array $data
    ): TaxComponent {
        return DB::transaction(function () use (
            $component,
            $data
        ) {
            $componentType =
                $data['component_type']
                ?? $component->component_type;

            $this->rules->validateComponentType(
                $componentType
            );

            $this->repository->update($component, [
                'name' =>
                    $data['name']
                    ?? $component->name,

                'component_type' =>
                    $componentType,

                'is_active' =>
                    $data['is_active']
                    ?? $component->is_active,
            ]);

            return $component->refresh();
        });
    }

    public function delete(
        TaxComponent $component
    ): bool {
        return DB::transaction(
            function () use ($component) {
                if (
                    $component
                        ->taxGroupComponents()
                        ->exists()
                ) {
                    throw new BusinessException(
                        'This tax component cannot be deleted because it is used by one or more tax groups.'
                    );
                }

                if (
                    $component
                        ->accountingMappings()
                        ->exists()
                ) {
                    throw new BusinessException(
                        'This tax component cannot be deleted because it is used by one or more accounting mappings.'
                    );
                }

                return $this->repository->delete(
                    $component
                );
            }
        );
    }
}