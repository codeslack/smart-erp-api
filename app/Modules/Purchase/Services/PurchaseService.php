<?php

namespace App\Modules\Purchase\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Modules\Purchase\Services\Actions\CreatePurchaseAction;
use App\Modules\Purchase\Services\Actions\UpdatePurchaseAction;
use App\Modules\Purchase\Services\Actions\ConfirmPurchaseAction;
use App\Modules\Purchase\Services\Actions\CancelPurchaseAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseService extends BaseService
{
    public function __construct(
        protected PurchaseRepositoryInterface $repository,
        protected CreatePurchaseAction $createAction,
        protected UpdatePurchaseAction $updateAction,
        protected ConfirmPurchaseAction $confirmAction,
        protected CancelPurchaseAction $cancelAction,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function findByUuid(string $uuid): ?Purchase
    {
        return $this->repository->findByUuid($uuid);
    }

    public function findByUuidOrFail(string $uuid): Purchase
    {
        $purchase = $this->repository->findByUuid($uuid);

        if (! $purchase) {
            throw new NotFoundException('Purchase not found.');
        }

        return $purchase;
    }

    public function find(int $id): ?Purchase
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Purchase
    {
        return $this->createAction->execute($data);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return $this->updateAction->execute($purchase, $data);
    }

    public function confirm(Purchase $purchase): Purchase
    {
        return $this->confirmAction->execute($purchase);
    }

    public function cancel(Purchase $purchase, ?string $reason = null): Purchase
    {
        return $this->cancelAction->execute($purchase, $reason);
    }

    public function delete(Purchase $purchase): bool
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (! $purchase->isDraft() && ! $purchase->isCancelled()) {
                throw new BusinessException('Only draft or cancelled purchases can be deleted.');
            }

            return $this->repository->delete($purchase);
        });
    }
}
