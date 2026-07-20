<?php

namespace App\Modules\AdvanceAllocation\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\SupplierPayment\Models\SupplierPayment;

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
        CustomerReceipt $receipt
    ): float {

        $allocated =
            $this->repository
                ->totalAllocatedFromSource(
                    CustomerReceipt::class,
                    $receipt->id
                );

        return max(
            0,
            $receipt->amount - $allocated
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Supplier Advance Balance
    |--------------------------------------------------------------------------
    */

    public function getAvailableSupplierAdvance(
        SupplierPayment $payment
    ): float {

        $allocated =
            $this->repository
                ->totalAllocatedFromSource(
                    SupplierPayment::class,
                    $payment->id
                );

        return max(
            0,
            $payment->amount - $allocated
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Advance → Sale
    |--------------------------------------------------------------------------
    */

    public function allocateCustomerAdvance(
        CustomerReceipt $receipt,
        Sale $sale,
        float $amount
    ): AdvanceAllocation {

        return DB::transaction(
            function () use (
                $receipt,
                $sale,
                $amount
            ) {

                $availableAdvance =
                    $this->getAvailableCustomerAdvance(
                        $receipt
                    );

                if (
                    $amount > $availableAdvance
                ) {
                    abort(
                        422,
                        'Customer advance balance is insufficient.'
                    );
                }

                $sale->refresh();

                if (
                    $amount > $sale->due_amount
                ) {
                    abort(
                        422,
                        'Allocation exceeds sale due amount.'
                    );
                }

                return AdvanceAllocation::create([

                    'tenant_id' =>
                        tenantId(),

                    'allocation_type' =>
                        'customer',

                    'source_type' =>
                        CustomerReceipt::class,

                    'source_id' =>
                        $receipt->id,

                    'target_type' =>
                        Sale::class,

                    'target_id' =>
                        $sale->id,

                    'allocated_amount' =>
                        $amount,

                    'allocated_at' =>
                        now(),

                    'created_by' =>
                        auth()->id(),
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
        SupplierPayment $payment,
        Purchase $purchase,
        float $amount
    ): AdvanceAllocation {

        return DB::transaction(
            function () use (
                $payment,
                $purchase,
                $amount
            ) {

                $availableAdvance =
                    $this->getAvailableSupplierAdvance(
                        $payment
                    );

                if (
                    $amount > $availableAdvance
                ) {
                    abort(
                        422,
                        'Supplier advance balance is insufficient.'
                    );
                }

                $purchase->refresh();

                if (
                    $amount > $purchase->due_amount
                ) {
                    abort(
                        422,
                        'Allocation exceeds purchase due amount.'
                    );
                }            

                return AdvanceAllocation::create([

                    'tenant_id' =>
                        tenantId(),

                    'allocation_type' =>
                        'supplier',

                    'source_type' =>
                        SupplierPayment::class,

                    'source_id' =>
                        $payment->id,

                    'target_type' =>
                        Purchase::class,

                    'target_id' =>
                        $purchase->id,

                    'allocated_amount' =>
                        $amount,

                    'allocated_at' =>
                        now(),

                    'created_by' =>
                        auth()->id(),
                ]);
            }
        );
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