<?php

namespace App\Modules\SupplierPayment\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Purchase\Models\Purchase;

use Illuminate\Validation\ValidationException;

use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Models\SupplierPaymentAllocation;

use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
use App\Modules\SupplierPayment\Enums\SupplierPaymentType;

use App\Modules\SupplierPayment\Validation\SupplierPaymentValidator;

use App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class SupplierPaymentService
{
    public function __construct(
        protected SupplierPaymentRepositoryInterface $repository,
        protected SupplierPaymentValidator $validator,
        protected AccountingPostingServiceInterface $postingService,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ): SupplierPayment {

        return $this->repository->find(
            $id
        );
    }

    public function create(
        array $data
    ): SupplierPayment {

        return DB::transaction(
            function () use ($data) {

                $allocations =
                    $data['allocations'] ?? [];

                unset(
                    $data['allocations']
                );

                $this->validator->validate(
                    $data,
                    $allocations
                );

                $paymentNo = nextDocumentNumber(
                    'supplier_payment',
                    'PAY'
                );                

                $payment =
                    $this->repository->create([

                        ...$data,

                        'payment_no' => $paymentNo,

                        'status' =>
                            SupplierPaymentStatus::DRAFT,
                    ]);

                $this->createAllocations(
                    $payment,
                    $allocations
                );

                return $this->find(
                    $payment->id
                );
            }
        );
    }

    public function update(
        int|string $id,
        array $data
    ): SupplierPayment {

        return DB::transaction(
            function () use (
                $id,
                $data
            ) {

                $payment =
                    $this->find($id);

                $this->ensureDraft(
                    $payment
                );

                $allocations =
                    $data['allocations'] ?? [];

                unset(
                    $data['allocations']
                );

                $this->validator->validate(
                    $data,
                    $allocations
                );

                $payment->update(
                    $data
                );

                $payment
                    ->allocations()
                    ->delete();

                $this->createAllocations(
                    $payment,
                    $allocations
                );

                return $this->find(
                    $payment->id
                );
            }
        );
    }

    public function confirm(
        SupplierPayment $payment
    ): SupplierPayment {

        return DB::transaction(
            function () use ($payment) {

                $payment =
                    SupplierPayment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->id
                        );

                $this->ensureDraft(
                    $payment
                );

                if (
                    $payment->payment_type ===
                    SupplierPaymentType::INVOICE
                ) {

                    foreach (
                        $payment->allocations as $allocation
                    ) {

                        $purchase =
                            Purchase::query()
                                ->lockForUpdate()
                                ->findOrFail(
                                    $allocation->purchase_id
                                );

                        $purchase->increment(
                            'paid_amount',
                            $allocation->allocated_amount
                        );

                        $purchase->decrement(
                            'due_amount',
                            $allocation->allocated_amount
                        );
                    }
                }

                $payment->update([

                    'status' =>
                        SupplierPaymentStatus::CONFIRMED,
                ]);

                $payment =
                    $this->find(
                        $payment->id
                    );

                $this->postingService
                    ->postSupplierPayment(
                        $payment
                    );

                return $payment;
            }
        );
    }

    public function cancel(
        SupplierPayment $payment
    ): SupplierPayment {

        $this->ensureDraft(
            $payment
        );

        $payment->update([

            'status' =>
                SupplierPaymentStatus::CANCELLED,
        ]);

        return $payment->fresh();
    }

    public function delete(
        int|string $id
    ): bool {

        $payment =
            $this->find($id);

        $this->ensureDraft(
            $payment
        );

        return (bool)
            $this->repository
                ->delete($id);
    }

    protected function createAllocations(
        SupplierPayment $payment,
        array $allocations
    ): void {

        foreach (
            $allocations as $allocation
        ) {

            SupplierPaymentAllocation::create([

                'tenant_id' =>
                    tenant()->id,

                'supplier_payment_id' =>
                    $payment->id,

                'purchase_id' =>
                    $allocation['purchase_id'],

                'allocated_amount' =>
                    $allocation['allocated_amount'],
            ]);
        }
    }

    protected function ensureDraft(
        SupplierPayment $payment
    ): void {

        if (
            $payment->status !==
            SupplierPaymentStatus::DRAFT
        ) {

            throw ValidationException::withMessages([
                'status' => [
                    'Only draft payments can be modified.'
                ]
            ]);
        }
    }
}
