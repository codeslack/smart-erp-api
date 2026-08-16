<?php

namespace App\Modules\Unit\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Services\BaseService;

use App\Modules\Unit\Models\Unit;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Unit\Repositories\Contracts\UnitRepositoryInterface;

class UnitService extends BaseService
{
    public function __construct(
        protected UnitRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate(
                $perPage
            );
    }

    public function create(
        array $data
    ): Unit {

        return DB::transaction(
            function () use ($data) {

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::UNIT
                    );

                return $this->repository
                    ->create(
                        $data
                    );
            }
        );
    }

    public function update(
        Unit $unit,
        array $data
    ): Unit {

        return DB::transaction(
            fn () => $this->repository
                ->update(
                    $unit,
                    $data
                )
        );
    }

    public function delete(
        Unit $unit
    ): bool {

        return DB::transaction(
            fn () => $this->repository
                ->delete(
                    $unit
                )
        );
    }

    public function findById(
        int $id
    ): ?Unit {

        return $this->repository
            ->findById(
                $id
            );
    }

    public function findByUuid(
        string $uuid
    ): ?Unit {

        return $this->repository
            ->findByUuid(
                $uuid
            );
    }

    public function findByCode(
        string $code
    ): ?Unit {

        return $this->repository
            ->findByCode(
                $code
            );
    }
}