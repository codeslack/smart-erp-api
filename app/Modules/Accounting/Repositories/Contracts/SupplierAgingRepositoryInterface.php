<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface SupplierAgingRepositoryInterface
{
    public function getReport(
        ?string $asOfDate = null
    ): array;
}