<?php

namespace App\Modules\AdvanceAllocation\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;

use App\Modules\AdvanceAllocation\Repositories\Contracts\AdvanceAllocationRepositoryInterface;

class AdvanceAllocationService
{
    public function __construct(
        protected AdvanceAllocationRepositoryInterface $repository
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Customer Advance Balance
    |--------------------------------------------------------------------------
    */

    public function getAvailableCustomerAdvance(
        Payment $payment
    ): float {

        $this->ensureCustomerAdvance($payment);

        $advanceAllocated =
            $this->repository->totalAllocatedFromSource(
                Payment::class,
                $payment->id
            );

        $directAllocated = (float) $payment
            ->allocations()
            ->sum('allocated_amount');

        return max(
            0,
            (float) $payment->amount
                - ($advanceAllocated + $directAllocated)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Supplier Advance Balance
    |--------------------------------------------------------------------------
    */

    public function getAvailableSupplierAdvance(
        Payment $payment
    ): float {

        $this->ensureSupplierAdvance($payment);

        $advanceAllocated =
            $this->repository->totalAllocatedFromSource(
                Payment::class,
                $payment->id
            );

        $directAllocated = (float) $payment
            ->allocations()
            ->sum('allocated_amount');

        return max(
            0,
            (float) $payment->amount
                - ($advanceAllocated + $directAllocated)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Advance → Sale
    |--------------------------------------------------------------------------
    */

    public function allocateCustomerAdvance(
        Payment $payment,
        Sale $sale,
        float $amount
    ): AdvanceAllocation {

        return DB::transaction(
            function () use (
                $payment,
                $sale,
                $amount
            ) {

                // Match Sale confirmation: lock the target document before source payments.
                $sale = Sale::query()
                    ->whereKey($sale->id)
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    throw new BusinessException(
                        'Sales invoice not found.'
                    );
                }

                $lockedPayment = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedPayment) {
                    throw new BusinessException(
                        'Customer advance payment not found.'
                    );
                }

                $this->ensureCustomerAdvance($lockedPayment);

                if (
                    $lockedPayment->status
                    !== DocumentStatusEnum::CONFIRMED->value
                ) {
                    throw new BusinessException(
                        'Only confirmed customer advances can be allocated.'
                    );
                }

                $payment = $lockedPayment;

                $availableAdvance = $this->getAvailableCustomerAdvance($payment);

                if ($amount <= 0 || $amount > $availableAdvance) {
                    throw new BusinessException(
                        'Customer advance balance is insufficient.'
                    );
                }

                if ($amount > $sale->due_amount) {
                    throw new BusinessException(
                        'Allocation exceeds sale due amount.'
                    );
                }

                return AdvanceAllocation::create([

                    'tenant_id' => tenantId(),

                    'allocation_type' => 'customer',

                    'source_type' => Payment::class,

                    'source_id' => $payment->id,

                    'target_type' => Sale::class,

                    'target_id' => $sale->id,

                    'allocated_amount' => $amount,

                    'allocated_at' => now(),

                    'created_by' => auth()->id(),
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Supplier Advance → Purchase
    |--------------------------------------------------------------------------
    */

    public function allocateSupplierAdvance(
        Payment $payment,
        Purchase $purchase,
        float $amount
    ): AdvanceAllocation {

        return DB::transaction(
            function () use (
                $payment,
                $purchase,
                $amount
            ) {

                // Match Purchase confirmation: lock the target document before source payments.
                $purchase = Purchase::query()
                    ->whereKey($purchase->id)
                    ->lockForUpdate()
                    ->first();

                if (! $purchase) {
                    throw new BusinessException(
                        'Purchase bill not found.'
                    );
                }

                $lockedPayment = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedPayment) {
                    throw new BusinessException(
                        'Supplier advance payment not found.'
                    );
                }

                $this->ensureSupplierAdvance($lockedPayment);

                if (
                    $lockedPayment->status
                    !== DocumentStatusEnum::CONFIRMED->value
                ) {
                    throw new BusinessException(
                        'Only confirmed supplier advances can be allocated.'
                    );
                }

                $payment = $lockedPayment;

                $availableAdvance = $this->getAvailableSupplierAdvance($payment);

                if ($amount <= 0 || $amount > $availableAdvance) {
                    throw new BusinessException(
                        'Supplier advance balance is insufficient.'
                    );
                }

                if ($amount > $purchase->due_amount) {
                    throw new BusinessException(
                        'Allocation exceeds purchase due amount.'
                    );
                }

                return AdvanceAllocation::create([

                    'tenant_id' => tenantId(),

                    'allocation_type' => 'supplier',

                    'source_type' => Payment::class,

                    'source_id' => $payment->id,

                    'target_type' => Purchase::class,

                    'target_id' => $purchase->id,

                    'allocated_amount' => $amount,

                    'allocated_at' => now(),

                    'created_by' => auth()->id(),
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Advance Purpose Validation
    |--------------------------------------------------------------------------
    */

    protected function ensureCustomerAdvance(
        Payment $payment
    ): void {

        if (
            $payment->purpose !==
            PaymentPurposeEnum::CUSTOMER_ADVANCE
        ) {
            throw new BusinessException(
                'Payment is not a customer advance.'
            );
        }
    }

    protected function ensureSupplierAdvance(
        Payment $payment
    ): void {

        if (
            $payment->purpose !==
            PaymentPurposeEnum::SUPPLIER_ADVANCE
        ) {
            throw new BusinessException(
                'Payment is not a supplier advance.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Release Allocation
    |--------------------------------------------------------------------------
    */

    public function releaseTarget(
        string $targetType,
        int $targetId
    ): void {

        $this->repository
            ->deleteByTarget(
                $targetType,
                $targetId
            );
    }
}
