<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\ChartOfAccountRepositoryInterface;

class ChartOfAccountService
{
    public function __construct(
        protected ChartOfAccountRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository
            ->query()
            ->with([
                'group',
                'parent',
            ])
            ->paginate();
    }

    public function find(int $id)
    {
        return $this->repository
            ->query()
            ->with([
                'group',
                'parent',
                'children',
            ])
            ->findOrFail($id);
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
        $account = $this->find(
            $id
        );

        abort_if(
            $account->is_system,
            422,
            'System accounts cannot be modified.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function delete(int $id)
    {
        $account = $this->find(
            $id
        );

        abort_if(
            $account->is_system,
            422,
            'System accounts cannot be deleted.'
        );

        abort_if(
            $account->children()->exists(),
            422,
            'Account has child accounts.'
        );

        return $this->repository->delete(
            $id
        );
    }
}