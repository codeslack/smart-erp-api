<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Repositories\Contracts\TaxGroupRepositoryInterface;

class TaxGroupRepository
    extends BaseRepository
    implements TaxGroupRepositoryInterface
{
    public function __construct(TaxGroup $model)
    {
        parent::__construct($model);
    }

    public function findForCalculation(
        int $taxGroupId,
        string $calculationDate,
    ): ?TaxGroup {
        return $this->model
            ->newQuery()
            ->with([
                'components' => function ($query) use ($calculationDate) {
                    $query
                        ->where('is_active', true)
                        ->whereDate('effective_from', '<=', $calculationDate)
                        ->where(function ($query) use ($calculationDate) {
                            $query
                                ->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $calculationDate);
                        })
                        ->whereHas('taxComponent', function ($query) {
                            $query->where('is_active', true);
                        })
                        ->orderBy('sort_order');
                },
                'components.taxComponent',
            ])
            ->whereKey($taxGroupId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $calculationDate)
            ->where(function ($query) use ($calculationDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $calculationDate);
            })
            ->first();
    }
}