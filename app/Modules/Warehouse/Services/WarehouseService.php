<?php

namespace App\Modules\Warehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Warehouse\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseService
{
    public function __construct(
        protected WarehouseRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate($perPage);
    }

    public function create(
        array $data
    ): Warehouse {

        return DB::transaction(
            function () use ($data) {

                if (
                    $data['is_default'] ?? false
                ) {

                    Warehouse::query()
                        ->update([
                            'is_default' => false,
                        ]);
                }

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::WAREHOUSE
                    );

                return $this->repository
                    ->create($data);
            }
        );
    }

    public function update(
        Warehouse $warehouse,
        array $data
    ): Warehouse {

        return DB::transaction(
            function () use (
                $warehouse,
                $data
            ) {

                if (
                    $data['is_default'] ?? false
                ) {

                    Warehouse::query()

                        ->where(
                            'id',
                            '!=',
                            $warehouse->id
                        )

                        ->update([
                            'is_default' => false,
                        ]);
                }

                return $this->repository
                    ->update(
                        $warehouse,
                        $data
                    );
            }
        );
    }

    public function delete(
        Warehouse $warehouse
    ): bool {

        return $this->repository
            ->delete($warehouse);
    }

    public function findById(
        int $id
    ): ?Warehouse {

        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Warehouse {

        return $this->repository
            ->findByUuid($uuid);
    }
}