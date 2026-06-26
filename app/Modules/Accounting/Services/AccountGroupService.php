<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Repositories\Contracts\AccountGroupRepositoryInterface;

class AccountGroupService
{
    public function __construct(
        protected AccountGroupRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(array $data)
    {
        return $this->repository->create(
            $data
        );
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
        $group = $this->find(
            $id
        );

        abort_if(
            $group->accounts()->exists(),
            422,
            'Account Group contains accounts.'
        );

        return $this->repository->delete(
            $id
        );
    }
}