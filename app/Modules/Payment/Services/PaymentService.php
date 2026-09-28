<?php

namespace App\Modules\Payment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Services\BaseService;

use App\Modules\Payment\Models\Payment;

use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationServiceInterface;
use App\Modules\Payment\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentService
    extends BaseService
    implements PaymentServiceInterface
{
    public function __construct(
        protected PaymentRepositoryInterface $payments,
        protected PaymentAllocationServiceInterface $allocationService
    ) {}

    public function create(array $data): Payment
    {
        $allocations = $data['allocations'] ?? [];

        unset($data['allocations']);

        $data['status'] =
            DocumentStatusEnum::DRAFT->value;

        return DB::transaction(function () use (
            $data,
            $allocations
        ) {
            $payment = $this->payments->create($data);

            if ($allocations) {
                $this->allocationService->createMany(
                    $payment,
                    $allocations
                );
            }

            return $payment->load('allocations');
        });
    }

    public function update(
        Payment $payment,
        array $data
    ): Payment {
        $hasAllocations =
            array_key_exists('allocations', $data);

        $allocations =
            $data['allocations'] ?? [];

        unset($data['allocations']);

        return DB::transaction(function () use (
            $payment,
            $data,
            $hasAllocations,
            $allocations
        ) {
            $payment = $this->payments->update(
                $payment,
                $data
            );

            if ($hasAllocations) {
                $this->allocationService
                    ->deleteByPaymentId(
                        $payment->id
                    );

                if ($allocations) {
                    $this->allocationService->createMany(
                        $payment,
                        $allocations
                    );
                }
            }

            return $payment->load('allocations');
        });
    }

    public function findByUuid(
        string $uuid
    ): Payment {
        return $this->payments
            ->findOrFail($uuid)
            ->load('allocations');
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->payments->paginate($perPage);
    }

    public function delete(
        Payment $payment
    ): bool {
        return $this->payments->delete($payment);
    }
}