<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PurchaseItemRepository extends BaseRepository implements PurchaseItemRepositoryInterface
{
    public function __construct(PurchaseItem $model)
    {
        parent::__construct($model);
    }

    public function getByPurchaseId(int $purchaseId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('purchase_id', $purchaseId)
            ->with(['product', 'warehouse', 'tax'])
            ->get();
    }

    public function deleteByPurchaseId(int $purchaseId): bool
    {
        return (bool) $this->model
            ->newQuery()
            ->where('purchase_id', $purchaseId)
            ->delete();
    }
}

