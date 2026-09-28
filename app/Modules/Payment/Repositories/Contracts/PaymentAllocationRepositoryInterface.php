<?php

namespace App\Modules\Payment\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PaymentAllocationRepositoryInterface
    extends BaseRepositoryInterface
{
    public function getByPaymentId(
        int $paymentId
    ): Collection;

    public function getByDocument(
        string $documentType,
        int $documentId
    ): Collection;

    public function getAllocatedAmount(
        string $documentType,
        int $documentId
    ): float;
}