<?php

namespace App\Modules\AdvanceAllocation\Services;

use App\Modules\Sales\Models\Sale;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;

use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptType;

class CustomerAdvanceAutoAdjustmentService
{
    public function __construct(
        protected AdvanceAllocationService $allocationService
    ) {}

    public function adjust(
        Sale $sale
    ): float {

        $totalAdjusted = 0;

        $sale = Sale::query()
            ->lockForUpdate()
            ->findOrFail(
                $sale->id
            );

        if (
            $sale->due_amount <= 0
        ) {
            return 0;
        }

        $advances =
            CustomerReceipt::query()

                ->where(
                    'tenant_id',
                    tenantId()
                )

                ->where(
                    'customer_id',
                    $sale->customer_id
                )

                ->where(
                    'status',
                    CustomerReceiptStatus::CONFIRMED
                )

                ->where(
                    'receipt_type',
                    CustomerReceiptType::ADVANCE
                )

                ->orderBy(
                    'receipt_date'
                )

                ->orderBy(
                    'id'
                )

                ->lockForUpdate()

                ->get();

        foreach (
            $advances as $advance
        ) {

            $availableAmount =
                $this->allocationService
                    ->getAvailableCustomerAdvance(
                        $advance
                    );

            if (
                $availableAmount <= 0
            ) {
                continue;
            }

            if (
                $sale->due_amount <= 0
            ) {
                break;
            }

            $allocationAmount =
                min(
                    $availableAmount,
                    $sale->due_amount
                );

            $this->allocationService
                ->allocateCustomerAdvance(

                    receipt:
                        $advance,

                    sale:
                        $sale,

                    amount:
                        $allocationAmount
                );

            $sale->increment(
                'paid_amount',
                $allocationAmount
            );

            $sale->decrement(
                'due_amount',
                $allocationAmount
            );

            $totalAdjusted +=
                $allocationAmount;
        }

        return $totalAdjusted;
    }
}
