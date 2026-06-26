<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface ReceivableSummaryRepositoryInterface
{
    public function getReport(): array;
}