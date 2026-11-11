<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;
use App\Modules\Sales\Services\Actions\CancelSaleAction;
use App\Modules\Sales\Services\Actions\ConfirmSaleAction;
use App\Modules\Sales\Services\Actions\CreateSaleAction;
use App\Modules\Sales\Services\Actions\UpdateSaleAction;
use App\Modules\Sales\Services\Validators\SaleValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected SaleRepositoryInterface $repository,
        protected CreateSaleAction $createAction,
        protected UpdateSaleAction $updateAction,
        protected ConfirmSaleAction $confirmAction,
        protected CancelSaleAction $cancelAction,
        protected SaleValidator $validator
    ) {}

    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function find(int|string $id): Sale
    {
        return $this->repository->find($id);
    }

    public function findByUuid(string $uuid): ?Sale
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): Sale
    {
        return $this->createAction->execute($data);
    }

    public function update(int|string|Sale $sale, array $data): Sale
    {
        $model = $sale instanceof Sale ? $sale : $this->find($sale);

        return $this->updateAction->execute($model, $data);
    }

    public function confirm(int|string|Sale $sale): Sale
    {
        $model = $sale instanceof Sale ? $sale : $this->find($sale);

        return $this->confirmAction->execute($model);
    }

    public function approve(Sale $sale): Sale
    {
        return $this->confirm($sale);
    }

    public function cancel(int|string|Sale $sale, ?string $reason = null): Sale
    {
        $model = $sale instanceof Sale ? $sale : $this->find($sale);

        return $this->cancelAction->execute($model, $reason);
    }

    public function delete(int|string|Sale $sale): bool
    {
        $model = $sale instanceof Sale ? $sale : $this->find($sale);

        return DB::transaction(function () use ($model) {
            $model = Sale::query()
                ->lockForUpdate()
                ->findOrFail($model->id);

            $this->validator->validateForDeletion($model);

            $model->items()->delete();

            return (bool) $model->delete();
        });
    }

    public function getItems(string $uuid): Collection
    {
        $sale = $this->findByUuid($uuid) ?? $this->find($uuid);

        return $sale->items()
            ->with(['product', 'variant', 'warehouse', 'unit', 'tax', 'batches', 'serials'])
            ->get();
    }
}
