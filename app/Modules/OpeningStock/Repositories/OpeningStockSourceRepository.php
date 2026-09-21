<?php

namespace App\Modules\OpeningStock\Repositories;

use Illuminate\Support\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\OpeningStock\Models\OpeningStockSource;

use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockSourceRepositoryInterface;

/**
 * @extends BaseRepository<OpeningStockSource>
 */
class OpeningStockSourceRepository
    extends BaseRepository
    implements OpeningStockSourceRepositoryInterface
{
    public function __construct(
        OpeningStockSource $model
    ) {
        parent::__construct($model);
    }

    public function findByOpeningStock(
        int $openingStockId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where(
                'opening_stock_id',
                $openingStockId
            )
            ->orderBy('id')
            ->get();
    }

    public function deleteByOpeningStock(
        int $openingStockId
    ): void {
        $this->model
            ->newQuery()
            ->where(
                'opening_stock_id',
                $openingStockId
            )
            ->delete();
    }
}