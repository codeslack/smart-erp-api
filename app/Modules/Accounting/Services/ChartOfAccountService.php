<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\ChartOfAccountRepositoryInterface;

class ChartOfAccountService
{
    public function __construct(
        protected ChartOfAccountRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return ChartOfAccount::query()
            ->with([
                'group',
                'parent',
            ])
            ->paginate();
    }

    public function find(int|string $id): ChartOfAccount
    {
        return ChartOfAccount::query()
            ->with([
                'group',
                'parent',
                'children',
            ])
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->whereKey($id);
                } else {
                    $q->where('uuid', $id);
                }
            })
            ->firstOrFail();
    }

    public function create(array $data)
    {
        $data = $this->resolveForeignKeys($data);

        // System accounts are reserved for trusted setup services.
        $data['is_system'] = false;

        return $this->repository->create(
            $data
        );
    }

    public function update(
        int|string $id,
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

        $data = $this->resolveForeignKeys($data);

        return $this->repository->update(
            $account,
            $data
        );
    }

    public function delete(int|string $id)
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
            $account
        );
    }

    protected function resolveForeignKeys(array $data): array
    {
        if (! empty($data['account_group_uuid']) && empty($data['account_group_id'])) {
            $group = AccountGroup::query()
                ->where('uuid', $data['account_group_uuid'])
                ->first();

            if ($group) {
                $data['account_group_id'] = $group->id;
            }

            unset($data['account_group_uuid']);
        }

        if (! empty($data['parent_account_uuid']) && empty($data['parent_id'])) {
            $parent = ChartOfAccount::query()
                ->where('uuid', $data['parent_account_uuid'])
                ->first();

            if ($parent) {
                $data['parent_id'] = $parent->id;
            }

            unset($data['parent_account_uuid']);
        }

        return $data;
    }
}
