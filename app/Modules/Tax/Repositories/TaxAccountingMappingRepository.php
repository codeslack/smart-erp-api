<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Tax\Models\TaxAccountingMapping;
use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;

class TaxAccountingMappingRepository
    extends BaseRepository
    implements TaxAccountingMappingRepositoryInterface
{
    public function __construct(
        TaxAccountingMapping $model
    ) {
        parent::__construct($model);
    }

    public function findForPosting(
        int $taxComponentId,
        string $direction,
        string $calculationDate,
    ): ?TaxAccountingMapping {
        return $this->model
            ->newQuery()
            ->with('account')
            ->where('tax_component_id', $taxComponentId)
            ->where('direction', $direction)
            ->where('is_active', true)
            ->whereDate(
                'effective_from',
                '<=',
                $calculationDate
            )
            ->where(function ($query) use ($calculationDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $calculationDate
                    );
            })
            ->first();
    }

    public function existsOverlappingPeriod(
        int $taxComponentId,
        string $direction,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): bool {
        $query = $this->model->newQuery()
            ->where(
                'tax_component_id',
                $taxComponentId
            )
            ->where(
                'direction',
                $direction
            );

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query
            ->where(function ($query) use (
                $effectiveFrom,
                $effectiveTo
            ) {
                if ($effectiveTo !== null) {
                    $query->where(
                        'effective_from',
                        '<=',
                        $effectiveTo
                    );
                }

                $query->where(function ($query) use (
                    $effectiveFrom
                ) {
                    $query->whereNull(
                        'effective_to'
                    )->orWhere(
                        'effective_to',
                        '>=',
                        $effectiveFrom
                    );
                });
            })
            ->exists();
    }
}