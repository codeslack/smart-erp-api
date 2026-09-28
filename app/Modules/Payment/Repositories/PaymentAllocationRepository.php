<?php

namespace App\Modules\Payment\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\Payment\Models\PaymentAllocation;

use App\Core\Repositories\BaseRepository;
use App\Modules\Payment\Repositories\Contracts\PaymentAllocationRepositoryInterface;

class PaymentAllocationRepository
    extends BaseRepository
    implements PaymentAllocationRepositoryInterface
{
    public function __construct(PaymentAllocation $model)
    {
        parent::__construct($model);
    }

    public function getByPaymentId(
        int $paymentId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('payment_id', $paymentId)
            ->get();
    }

    public function getByDocument(
        string $documentType,
        int $documentId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->get();
    }

    public function getAllocatedAmount(
        string $documentType,
        int $documentId
    ): float {
        return (float) $this->model
            ->newQuery()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->sum('allocated_amount');
    }
}