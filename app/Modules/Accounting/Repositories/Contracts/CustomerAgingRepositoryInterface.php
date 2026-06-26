<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface CustomerAgingRepositoryInterface
{
    public function getReport(
        ?string $asOfDate = null
    ): array;
}