<?php

namespace App\Modules\OpeningStock\Repositories\Contracts;

use Illuminate\Support\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\OpeningStock\Models\OpeningStockSource;

interface OpeningStockSourceRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByOpeningStock(
        int $openingStockId
    ): Collection;

    public function deleteByOpeningStock(
        int $openingStockId
    ): void;
}