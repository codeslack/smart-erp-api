<?php

namespace App\Modules\Area\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\Area\Models\Area;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Area\Repositories\Contracts\AreaRepositoryInterface;

class AreaService
{
    public function __construct(
        protected AreaRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate($perPage);
    }

    public function create(
        array $data
    ): Area {

        return DB::transaction(
            function () use ($data) {

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::AREA
                    );

                return $this->repository
                    ->create($data);
            }
        );
    }

    public function update(
        Area $area,
        array $data
    ): Area {

        return DB::transaction(
            function () use (
                $area,
                $data
            ) {

                return $this->repository
                    ->update(
                        $area,
                        $data
                    );
            }
        );
    }

    public function delete(
        Area $area
    ): bool {

        return DB::transaction(
            fn () => $this->repository
                ->delete(
                    $area
                )
        );
    }

    public function findById(
        int $id
    ): ?Area {

        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Area {

        return $this->repository
            ->findByUuid($uuid);
    }

    public function findByCode(
        string $code
    ): ?Area {

        return $this->repository
            ->findByCode(
                $code
            );
    }
}