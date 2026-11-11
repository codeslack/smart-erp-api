<?php

namespace App\Modules\SalesReturn\Services;

use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;
use App\Modules\SalesReturn\Services\Actions\CancelSalesReturnAction;
use App\Modules\SalesReturn\Services\Actions\ConfirmSalesReturnAction;
use App\Modules\SalesReturn\Services\Actions\CreateSalesReturnAction;
use App\Modules\SalesReturn\Services\Actions\UpdateSalesReturnAction;
use App\Modules\SalesReturn\Services\Validators\SalesReturnValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function __construct(
        protected SalesReturnRepositoryInterface $repository,
        protected CreateSalesReturnAction $createAction,
        protected UpdateSalesReturnAction $updateAction,
        protected ConfirmSalesReturnAction $confirmAction,
        protected CancelSalesReturnAction $cancelAction,
        protected SalesReturnValidator $validator
    ) {}

    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function find(int|string $id): SalesReturn
    {
        return $this->repository->find($id);
    }

    public function findByUuid(string $uuid): ?SalesReturn
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): SalesReturn
    {
        return $this->createAction->execute($data);
    }

    public function update(int|string|SalesReturn $return, array $data): SalesReturn
    {
        $model = $return instanceof SalesReturn ? $return : $this->find($return);

        return $this->updateAction->execute($model, $data);
    }

    public function confirm(int|string|SalesReturn $return): SalesReturn
    {
        $model = $return instanceof SalesReturn ? $return : $this->find($return);

        return $this->confirmAction->execute($model);
    }

    public function approve(SalesReturn $return): SalesReturn
    {
        return $this->confirm($return);
    }

    public function cancel(int|string|SalesReturn $return, ?string $reason = null): SalesReturn
    {
        $model = $return instanceof SalesReturn ? $return : $this->find($return);

        return $this->cancelAction->execute($model, $reason);
    }

    public function delete(int|string|SalesReturn $return): bool
    {
        $model = $return instanceof SalesReturn ? $return : $this->find($return);

        return DB::transaction(function () use ($model) {
            $model = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($model->id);

            $this->validator->validateForDeletion($model);

            $model->items()->delete();

            return (bool) $model->delete();
        });
    }

    public function getItems(string $uuid): Collection
    {
        $return = $this->findByUuid($uuid) ?? $this->find($uuid);

        return $return->items()
            ->with(['product', 'variant', 'warehouse', 'unit', 'tax', 'batches', 'serials'])
            ->get();
    }
}
