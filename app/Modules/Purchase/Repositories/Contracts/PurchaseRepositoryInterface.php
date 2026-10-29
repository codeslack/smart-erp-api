<?php

namespace App\Modules\Purchase\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\Purchase\Models\Purchase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PurchaseRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByPurchaseNo(string $purchaseNo): ?Purchase;

    public function getBySupplierId(int $supplierId): Collection;

    public function getByGoodsReceiptNoteId(int $goodsReceiptNoteId): ?Purchase;

    public function getByStatus(string $status): Collection;
}
