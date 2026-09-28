<?php

namespace App\Modules\Payment\Services;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Services\BaseService;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

use App\Modules\Payment\Services\Contracts\PaymentAllocationServiceInterface;
use App\Modules\Payment\Repositories\Contracts\PaymentAllocationRepositoryInterface;

class PaymentAllocationService
    extends BaseService
    implements PaymentAllocationServiceInterface
{
    public function __construct(
        protected PaymentAllocationRepositoryInterface $allocations
    ) {}

    public function create(
        array $data
    ): PaymentAllocation {
        return $this->allocations->create($data);
    }

    public function createMany(
        Payment $payment,
        array $allocations
    ): void {
        foreach ($allocations as $allocation) {
            $this->allocations->create([
                'payment_id' => $payment->id,
                'document_type' =>
                    $allocation['document_type'],
                'document_id' =>
                    $allocation['document_id'],
                'allocated_amount' =>
                    $allocation['allocated_amount'],
            ]);
        }
    }

    public function deleteByPaymentId(
        int $paymentId
    ): void {
        $allocations = $this->allocations
            ->getByPaymentId($paymentId);

        foreach ($allocations as $allocation) {
            $this->allocations->delete(
                $allocation
            );
        }
    }

    public function getByPaymentId(
        int $paymentId
    ): Collection {
        return $this->allocations->getByPaymentId(
            $paymentId
        );
    }

    public function getByDocument(
        string $documentType,
        int $documentId
    ): Collection {
        return $this->allocations->getByDocument(
            $documentType,
            $documentId
        );
    }

    public function getAllocatedAmount(
        string $documentType,
        int $documentId
    ): float {
        return $this->allocations->getAllocatedAmount(
            $documentType,
            $documentId
        );
    }
}