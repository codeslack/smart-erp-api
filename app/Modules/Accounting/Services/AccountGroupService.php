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

    public function find(int|string $id): AccountGroup
    {
        if (is_numeric($id)) {
            return $this->repository->findById($id);
        }

        return AccountGroup::query()
            ->where('uuid', $id)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        return $this->repository->create(
            $data
        );
    }

    public function update(
        int|string $id,
        array $data
    ) {
        $group = $this->find(
            $id
        );

        return $this->repository->update(
            $group,
            $data
        );
    }

    public function delete(int|string $id)
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
            $group
        );
    }
}
