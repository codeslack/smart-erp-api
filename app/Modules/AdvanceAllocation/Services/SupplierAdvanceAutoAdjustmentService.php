<?php

namespace App\Modules\AdvanceAllocation\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\SupplierPayment\Models\SupplierPayment;

use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
use App\Modules\SupplierPayment\Enums\SupplierPaymentType;

class SupplierAdvanceAutoAdjustmentService
{
    public function __construct(
        protected AdvanceAllocationService $allocationService
    ) {}

    public function adjust(
        Purchase $purchase
    ): float {

        $totalAdjusted = 0;

        DB::transaction(function () use (
            $purchase,
            &$totalAdjusted
        ) {

            $purchase = Purchase::query()

                ->lockForUpdate()

                ->findOrFail(
                    $purchase->id
                );

            if (
                $purchase->due_amount <= 0
            ) {
                return;
            }

            $advances =
                SupplierPayment::query()

                    ->where(
                        'tenant_id',
                        tenantId()
                    )

                    ->where(
                        'supplier_id',
                        $purchase->supplier_id
                    )

                    ->where(
                        'status',
                        SupplierPaymentStatus::CONFIRMED
                    )

                    ->where(
                        'payment_type',
                        SupplierPaymentType::ADVANCE
                    )

                    ->orderBy(
                        'payment_date'
                    )

                    ->orderBy(
                        'id'
                    )

                    ->lockForUpdate()

                    ->get();

            foreach ($advances as $advance) {

                $availableAmount =
                    $this->allocationService
                        ->getAvailableSupplierAdvance(
                            $advance
                        );

                if (
                    $availableAmount <= 0
                ) {
                    continue;
                }

                if (
                    $purchase->due_amount <= 0
                ) {
                    break;
                }

                $allocationAmount =
                    min(
                        $availableAmount,
                        $purchase->due_amount
                    );

                $this->allocationService
                    ->allocateSupplierAdvance(

                        payment:
                            $advance,

                        purchase:
                            $purchase,

                        amount:
                            $allocationAmount
                    );

                $purchase->increment(
                    'paid_amount',
                    $allocationAmount
                );

                $purchase->decrement(
                    'due_amount',
                    $allocationAmount
                );

                $totalAdjusted +=
                    $allocationAmount;
            }

            $purchase->refresh();
        });

        return $totalAdjusted;
    }
}