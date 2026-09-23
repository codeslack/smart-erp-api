<?php

namespace App\Modules\SupplierOpeningBill\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

interface SupplierOpeningBillRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?SupplierOpeningBill;

    public function findBySupplierAndBillNo(
        int $supplierId,
        string $billNo
    ): ?SupplierOpeningBill;

    public function findBySupplier(
        int $supplierId
    ): Collection;

    public function outstandingBills(
        int $supplierId
    ): Collection;
}
