<?php

namespace App\Modules\AdvanceAllocation\Repositories\Contracts;

interface AdvanceAllocationRepositoryInterface
{
    public function create(
        array $data
    );

    public function totalAllocatedFromSource(
        string $sourceType,
        int $sourceId
    ): float;

    public function deleteByTarget(
        string $targetType,
        int $targetId
    ): void;
}