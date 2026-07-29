<?php

namespace App\Modules\Brand\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\Brand\Models\Brand;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Brand\Repositories\Contracts\BrandRepositoryInterface;

class BrandService
{
    public function __construct(
        protected BrandRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate($perPage);
    }

    public function create(
        array $data
    ): Brand {

        return DB::transaction(
            function () use ($data) {

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::BRAND
                    );

                return $this->repository
                    ->create($data);
            }
        );
    }

    public function update(
        Brand $brand,
        array $data
    ): Brand {

        return DB::transaction(
            function () use (
                $brand,
                $data
            ) {

                return $this->repository
                    ->update(
                        $brand,
                        $data
                    );
            }
        );
    }

    public function delete(
        Brand $brand
    ): bool {

        return DB::transaction(
            fn () => $this->repository
                ->delete(
                    $brand
                )
        );
    }

    public function findById(
        int $id
    ): ?Brand {

        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Brand {

        return $this->repository
            ->findByUuid($uuid);
    }

    public function findByCode(
        string $code
    ): ?Brand {

        return $this->repository
            ->findByCode(
                $code
            );
    }
}