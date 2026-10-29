<?php

namespace App\Modules\PurchaseReturn\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use App\Modules\PurchaseReturn\Services\Actions\CancelPurchaseReturnAction;
use App\Modules\PurchaseReturn\Services\Actions\ConfirmPurchaseReturnAction;
use App\Modules\PurchaseReturn\Services\Actions\CreatePurchaseReturnAction;
use App\Modules\PurchaseReturn\Services\Actions\UpdatePurchaseReturnAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService extends BaseService
{
    public function __construct(
        protected PurchaseReturnRepositoryInterface $repository,
        protected CreatePurchaseReturnAction $createAction,
        protected UpdatePurchaseReturnAction $updateAction,
        protected ConfirmPurchaseReturnAction $confirmAction,
        protected CancelPurchaseReturnAction $cancelAction,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function findByUuid(string $uuid): ?PurchaseReturn
    {
        return $this->repository->findByUuid($uuid);
    }

    public function findByUuidOrFail(string $uuid): PurchaseReturn
    {
        $purchaseReturn = $this->repository->findByUuid($uuid);

        if (! $purchaseReturn) {
            throw new NotFoundException('Purchase return not found.');
        }

        return $purchaseReturn;
    }

    public function find(int|string $id): ?PurchaseReturn
    {
        return $this->repository->find($id);
    }

    public function create(array $data): PurchaseReturn
    {
        return $this->createAction->execute($data);
    }

    public function update(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn
    {
        return $this->updateAction->execute($purchaseReturn, $data);
    }

    public function confirm(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        return $this->confirmAction->execute($purchaseReturn);
    }

    public function approve(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        return $this->confirm($purchaseReturn);
    }

    public function cancel(PurchaseReturn $purchaseReturn, ?string $reason = null): PurchaseReturn
    {
        return $this->cancelAction->execute($purchaseReturn, $reason);
    }

    public function delete(PurchaseReturn $purchaseReturn): bool
    {
        if (! $purchaseReturn->isDraft()) {
            throw new BusinessException('Only draft purchase returns can be deleted.');
        }

        return (bool) DB::transaction(function () use ($purchaseReturn) {
            $purchaseReturn->batches()->delete();
            $purchaseReturn->serials()->delete();
            $purchaseReturn->items()->delete();

            return $purchaseReturn->delete();
        });
    }
}
