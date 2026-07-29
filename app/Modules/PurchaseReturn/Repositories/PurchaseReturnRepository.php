<?php

namespace App\Modules\PurchaseReturn\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;

class PurchaseReturnRepository 
    extends BaseRepository 
    implements PurchaseReturnRepositoryInterface
{
    public function __construct(
        PurchaseReturn $model
    ) {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15)
    {
        return $this->query()
            ->with([
                'supplier',
                'purchase',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function find(int|string $id)
    {
        return $this->query()
            ->with([
                'supplier',
                'purchase',
                'items.product',
                'items.warehouse',
                'items.purchaseItem',
            ])
            ->findOrFail($id);
    }
}
