<?php

namespace App\Modules\OpeningStock\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockRepositoryInterface;

class OpeningStockService
{
    public function __construct(
        protected OpeningStockRepositoryInterface $repository
    ) {
    }

    public function create(
        array $data
    ): OpeningStock {

        return DB::transaction(
            function () use ($data) {

                return $this->repository->create(
                    $data
                );
            }
        );
    }

    public function update(
        OpeningStock $openingStock,
        array $data
    ): OpeningStock {

        return DB::transaction(
            function () use (
                $openingStock,
                $data
            ) {

                return $this->repository->update(
                    $openingStock,
                    $data
                );
            }
        );
    }

    public function delete(
        OpeningStock $openingStock
    ): bool {

        return DB::transaction(
            function () use (
                $openingStock
            ) {

                return $this->repository->delete(
                    $openingStock
                );
            }
        );
    }
}