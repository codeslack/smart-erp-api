<?php

namespace App\Modules\Supplier\Services;

use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierService
{
    public function __construct(
        protected SupplierRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate(20);
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(array $data)
    {
        return $this->repository->create($data);
    }

    public function update(
        int $id,
        array $data
    ) {
        return $this->repository->update(
            $id,
            $data
        );
    }

    public function delete(int $id)
    {
        return $this->repository->delete($id);
    }
}