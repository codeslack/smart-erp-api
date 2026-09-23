<?php

namespace App\Modules\CustomerOpeningBill\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

interface CustomerOpeningBillRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?CustomerOpeningBill;

    public function findByCustomerAndBillNo(
        int $customerId,
        string $billNo
    ): ?CustomerOpeningBill;

    public function findByCustomer(
        int $customerId
    ): Collection;

    public function outstandingBills(
        int $customerId
    ): Collection;
}