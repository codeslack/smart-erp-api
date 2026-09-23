<?php

namespace App\Modules\Supplier\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Services\BaseService;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierService extends BaseService
{
    public function __construct(
        protected SupplierRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage);
    }

    public function create(array $data): Supplier
    {
        return DB::transaction(
            fn () => $this->repository->create($data)
        );
    }

    public function update(
        Supplier $supplier,
        array $data
    ): Supplier {
        return DB::transaction(
            fn () => $this->repository->update(
                $supplier,
                $data
            )
        );
    }

    public function delete(Supplier $supplier): bool
    {
        return DB::transaction(
            fn () => $this->repository->delete($supplier)
        );
    }

    public function findById(int $id): ?Supplier
    {
        return $this->repository->findById($id);
    }

    public function findByUuid(string $uuid): ?Supplier
    {
        return $this->repository->findByUuid($uuid);
    }
}