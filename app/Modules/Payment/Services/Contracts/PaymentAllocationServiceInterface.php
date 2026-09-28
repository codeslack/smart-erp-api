<?php

namespace App\Modules\Payment\Services\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

interface PaymentAllocationServiceInterface
{
    public function create(
        array $data
    ): PaymentAllocation;

    public function createMany(
        Payment $payment,
        array $allocations
    ): void;

    public function deleteByPaymentId(
        int $paymentId
    ): void;

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