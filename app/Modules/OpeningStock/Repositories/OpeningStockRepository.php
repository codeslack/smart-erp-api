<?php

namespace App\Modules\OpeningStock\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockRepositoryInterface;

/**
 * @extends BaseRepository<OpeningStock>
 */
class OpeningStockRepository
    extends BaseRepository
    implements OpeningStockRepositoryInterface
{
    public function __construct(
        OpeningStock $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByDocumentNo(
        string $documentNo
    ): ?OpeningStock {

        return $this->model
            ->newQuery()

            ->where(
                'document_no',
                $documentNo
            )

            ->first();
    }
}