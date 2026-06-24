<?php

namespace App\Modules\SupplierPayment\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
use App\Modules\SupplierPayment\Models\SupplierPaymentAllocation;
use App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface;

class SupplierPaymentService
{
    public function __construct(
        protected SupplierPaymentRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            $allocations = $data['allocations'];

            unset($data['allocations']);

            $totalAllocated = collect(
                $allocations
            )->sum(
                'allocated_amount'
            );

            abort_if(
                $totalAllocated != $data['amount'],
                422,
                'Payment amount must equal total allocated amount.'
            );

            $nextId = (
                SupplierPayment::max('id') ?? 0
            ) + 1;

            $data['payment_no'] = sprintf(
                'PAY-%06d',
                $nextId
            );

            $data['status'] = SupplierPaymentStatus::DRAFT;

            $payment = $this->repository->create(
                $data
            );

            foreach ($allocations as $allocation) {

                $purchase = Purchase::findOrFail(
                    $allocation['purchase_id']
                );

                abort_if(
                    $purchase->supplier_id != $payment->supplier_id,
                    422,
                    'Purchase does not belong to supplier.'
                );

                abort_if(
                    $allocation['allocated_amount']
                        >
                        $purchase->due_amount,
                    422,
                    'Allocated amount exceeds invoice due amount.'
                );

                SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'purchase_id'         => $purchase->id,
                    'allocated_amount'    => $allocation['allocated_amount'],
                ]);
            }

            return $payment->load(
                'allocations.purchase'
            );
        });
    }

    public function confirm(
        SupplierPayment $payment
    ) {
        return DB::transaction(function () use ($payment) {

            abort_if(
                $payment->status !== SupplierPaymentStatus::DRAFT,
                422,
                'Only draft payments can be confirmed.'
            );

            $payment->loadMissing(
                'allocations'
            );

            foreach (
                $payment->allocations as $allocation
            ) {

                $purchase = Purchase::findOrFail(
                    $allocation->purchase_id
                );

                abort_if(
                    $allocation->allocated_amount
                        >
                        $purchase->due_amount,
                    422,
                    'Invoice due amount exceeded.'
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

            $payment->update([
                'status' => SupplierPaymentStatus::CONFIRMED,
            ]);

            return $payment->fresh(
                'allocations.purchase'
            );
        });
    }

    public function cancel(
        SupplierPayment $payment
    ) {
        abort_if(
            $payment->status !== SupplierPaymentStatus::DRAFT,
            422,
            'Only draft payments can be cancelled.'
        );

        $payment->update([
            'status' => SupplierPaymentStatus::CANCELLED,
        ]);

        return $payment->fresh();
    }

    public function delete(
        int $id
    ) {
        $payment = $this->find(
            $id
        );

        abort_if(
            $payment->status !== SupplierPaymentStatus::DRAFT,
            422,
            'Only draft payments can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
