<?php

namespace App\Modules\SupplierPayment\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface;

class SupplierPaymentRepository extends BaseRepository implements SupplierPaymentRepositoryInterface
{
    public function __construct(
        SupplierPayment $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function find(
        int|string $id
    )
    {
        return $this->model
            ->with([
                'supplier',
                'allocations.purchase',
            ])
            ->findOrFail(
                $id
            );
    }
}