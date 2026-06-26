<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface PayableSummaryRepositoryInterface
{
    public function getReport(): array;
}
