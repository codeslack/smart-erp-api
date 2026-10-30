<?php

namespace App\Modules\SalesOrder\Services;

use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Repositories\Contracts\SalesOrderRepositoryInterface;
use App\Modules\SalesOrder\Services\Actions\CancelSalesOrderAction;
use App\Modules\SalesOrder\Services\Actions\ConfirmSalesOrderAction;
use App\Modules\SalesOrder\Services\Actions\CreateSalesOrderAction;
use App\Modules\SalesOrder\Services\Actions\UpdateSalesOrderAction;
use App\Modules\SalesOrder\Services\Validators\SalesOrderValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        protected SalesOrderRepositoryInterface $repository,
        protected CreateSalesOrderAction $createAction,
        protected UpdateSalesOrderAction $updateAction,
        protected ConfirmSalesOrderAction $confirmAction,
        protected CancelSalesOrderAction $cancelAction,
        protected SalesOrderValidator $validator
    ) {}

    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function find(int|string $id): SalesOrder
    {
        return $this->repository->find($id);
    }

    public function findByUuid(string $uuid): ?SalesOrder
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): SalesOrder
    {
        return $this->createAction->execute($data);
    }

    public function update(int|string|SalesOrder $order, array $data): SalesOrder
    {
        $salesOrder = $order instanceof SalesOrder ? $order : $this->find($order);

        return $this->updateAction->execute($salesOrder, $data);
    }

    public function confirm(int|string|SalesOrder $order): SalesOrder
    {
        $salesOrder = $order instanceof SalesOrder ? $order : $this->find($order);

        return $this->confirmAction->execute($salesOrder);
    }

    public function approve(SalesOrder $order): SalesOrder
    {
        return $this->confirm($order);
    }

    public function cancel(int|string|SalesOrder $order, ?string $reason = null): SalesOrder
    {
        $salesOrder = $order instanceof SalesOrder ? $order : $this->find($order);

        return $this->cancelAction->execute($salesOrder, $reason);
    }

    public function delete(int|string|SalesOrder $order): bool
    {
        $salesOrder = $order instanceof SalesOrder ? $order : $this->find($order);
        $this->validator->validateForDeletion($salesOrder);

        return DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();

            return (bool) $salesOrder->delete();
        });
    }

    public function getItems(string $uuid): Collection
    {
        $salesOrder = $this->findByUuid($uuid) ?? $this->find($uuid);

        return $salesOrder->items()
            ->with(['product', 'warehouse', 'unit', 'taxGroup'])
            ->get();
    }
}
