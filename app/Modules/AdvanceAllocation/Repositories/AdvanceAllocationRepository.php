<?php

namespace App\Modules\AdvanceAllocation\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;

use App\Modules\AdvanceAllocation\Repositories\Contracts\AdvanceAllocationRepositoryInterface;

class AdvanceAllocationRepository
    extends BaseRepository
    implements AdvanceAllocationRepositoryInterface
{
    public function __construct(
        AdvanceAllocation $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function totalAllocatedFromSource(
        string $sourceType,
        int $sourceId
    ): float {

        return (float)
            $this->model

                ->where(
                    'source_type',
                    $sourceType
                )

                ->where(
                    'source_id',
                    $sourceId
                )

                ->sum(
                    'allocated_amount'
                );
    }

    public function deleteByTarget(
        string $targetType,
        int $targetId
    ): void {

        $this->model

            ->where(
                'target_type',
                $targetType
            )

            ->where(
                'target_id',
                $targetId
            )

            ->delete();
    }
}