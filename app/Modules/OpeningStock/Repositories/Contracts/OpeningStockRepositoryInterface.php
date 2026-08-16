<?php

namespace App\Modules\OpeningStock\Repositories\Contracts;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface OpeningStockRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByDocumentNo(
        string $documentNo
    ): ?OpeningStock;
}