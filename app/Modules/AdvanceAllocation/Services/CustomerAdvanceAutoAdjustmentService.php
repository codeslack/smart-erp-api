<?php

namespace App\Modules\AdvanceAllocation\Services;

use App\Modules\Sales\Models\Sale;

use App\Modules\Payment\Models\Payment;

use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;

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
            Payment::query()

            ->where(
                'tenant_id',
                tenantId()
            )

            ->where(
                'party_type',
                PaymentPartyTypeEnum::CUSTOMER
            )

            ->where(
                'party_id',
                $sale->customer_id
            )

            ->where(
                'status',
                PaymentStatusEnum::CONFIRMED
            )

            ->where(
                'payment_type',
                PaymentTypeEnum::RECEIPT
            )

            ->where(
                'purpose',
                PaymentPurposeEnum::CUSTOMER_ADVANCE
            )

            ->orderBy(
                'payment_date'
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

                    payment: $advance,

                    sale: $sale,

                    amount: $allocationAmount
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