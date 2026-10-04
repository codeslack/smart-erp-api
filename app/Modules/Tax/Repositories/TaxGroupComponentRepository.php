<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tax\Repositories\Contracts\TaxGroupComponentRepositoryInterface;

class TaxGroupComponentRepository
    extends BaseRepository
    implements TaxGroupComponentRepositoryInterface
{
    public function __construct(
        TaxGroupComponent $model
    ) {
        parent::__construct($model);
    }

    public function existsOverlappingPeriod(
        int $taxGroupId,
        int $taxComponentId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): bool {
        $query = $this->model->newQuery()
            ->where('tax_group_id', $taxGroupId)
            ->where('tax_component_id', $taxComponentId);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query
            ->where(function ($query) use (
                $effectiveFrom,
                $effectiveTo
            ) {
                /*
                 * Existing record:
                 * existing_from <= new_to
                 * AND
                 * existing_to >= new_from
                 */

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
