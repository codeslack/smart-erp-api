<?php

namespace App\Modules\PurchaseOrder\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PurchaseOrderRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?PurchaseOrder;

    public function findByOrderNumber(
        string $orderNumber
    ): ?PurchaseOrder;

    public function paginateWithFilters(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator;

    public function getWithRelations(
        string $uuid,
        array $relations = []
    ): ?PurchaseOrder;

    public function getItems(
        string $uuid
    ): Collection;

    public function findItemForReceiptUpdate(
        int $itemId
    ): ?PurchaseOrderItem;
}