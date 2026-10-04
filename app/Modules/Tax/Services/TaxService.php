<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Tax\Domain\TaxRules;
use App\Modules\Tax\Models\Tax;

use App\Modules\Tax\Repositories\Contracts\TaxRepositoryInterface;

class TaxService
{
    public function __construct(
        protected TaxRepositoryInterface $repository,
        protected TaxRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate(
            $perPage
        );
    }

    public function findByUuid(
        string $uuid
    ): ?Tax {
        return $this->repository->findByUuid(
            $uuid
        );
    }

    public function create(array $data): Tax
    {
        return DB::transaction(function () use ($data) {
            $effectiveFrom =
                $data['effective_from'];

            $effectiveTo =
                $data['effective_to'] ?? null;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validateTaxType(
                $data['tax_type']
            );

            return $this->repository->create([
                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::TAX
                ),

                'name' =>
                    $data['name'],

                'tax_type' =>
                    $data['tax_type'],

                'description' =>
                    $data['description'] ?? null,

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
        Tax $tax,
        array $data
    ): Tax {
        return DB::transaction(function () use (
            $tax,
            $data
        ) {
            $effectiveFrom =
                $data['effective_from']
                ?? $tax->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $tax->effective_to?->toDateString();

            $taxType =
                $data['tax_type']
                ?? $tax->tax_type;

            $name =
                $data['name']
                ?? $tax->name;

            $description = array_key_exists(
                'description',
                $data
            )
                ? $data['description']
                : $tax->description;

            $isActive =
                $data['is_active']
                ?? $tax->is_active;

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validateTaxType(
                $taxType
            );

            $this->repository->update(
                $tax,
                [
                    'name' =>
                        $name,

                    'tax_type' =>
                        $taxType,

                    'description' =>
                        $description,

                    'is_active' =>
                        $isActive,

                    'effective_from' =>
                        $effectiveFrom,

                    'effective_to' =>
                        $effectiveTo,
                ]
            );

            return $tax->refresh();
        });
    }

    public function delete(
        Tax $tax
    ): bool {
        return DB::transaction(function () use ($tax) {
            if ($tax->groups()->exists()) {
                throw new BusinessException(
                    'This tax cannot be deleted because it is used by one or more tax groups.'
                );
            }

            return $this->repository->delete(
                $tax
            );
        });
    }
}