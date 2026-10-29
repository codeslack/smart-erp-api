<?php

namespace App\Modules\Purchase\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface PurchaseItemRepositoryInterface extends BaseRepositoryInterface
{
    public function getByPurchaseId(int $purchaseId): Collection;

    public function deleteByPurchaseId(int $purchaseId): bool;
}

