<?php

namespace App\Modules\SupplierPayment\Repositories\Contracts;

use App\Core\Contracts\BaseRepositoryInterface;

interface SupplierPaymentRepositoryInterface
    extends BaseRepositoryInterface
{
    public function paginate(int $perPage = 15);

    public function find(int|string $id);
}
