<?php

namespace App\Modules\AdvanceAllocation\Services;

use App\Core\Exceptions\BusinessException;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
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

    public function reverse(Sale $sale): float
    {
        $sale = Sale::query()
            ->lockForUpdate()
            ->findOrFail($sale->id);

        $allocations = AdvanceAllocation::query()
            ->where('target_type', Sale::class)
            ->where('target_id', $sale->id)
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            return 0;
        }

        $allocatedAmount = number_format(
            (float) $allocations->sum('allocated_amount'),
            4,
            '.',
            ''
        );

        $remainingPaidAmount = bcsub(
            (string) $sale->paid_amount,
            $allocatedAmount,
            4
        );

        if (bccomp($remainingPaidAmount, '0', 4) !== 0) {
            throw new BusinessException(
                'The Sale balance does not match its customer advance allocations.'
            );
        }

        $dueAmount = bcadd(
            (string) $sale->due_amount,
            $allocatedAmount,
            4
        );

        $this->allocationService->releaseTarget(
            Sale::class,
            $sale->id
        );

        $sale->update([
            'paid_amount' => '0.0000',
            'due_amount' => $dueAmount,
        ]);

        return (float) $allocatedAmount;
    }
}