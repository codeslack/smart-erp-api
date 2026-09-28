<?php

namespace App\Modules\Payment\Validation;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\Payment\Enums\PaymentTypeEnum;

use Illuminate\Validation\ValidationException;

class PaymentValidator
{
    public function validate(
        array $data,
        array $allocations
    ): void {

        $type =
            PaymentTypeEnum::from(
                $data['payment_type']
            );

        match ($type) {

            PaymentTypeEnum::INVOICE =>
            $this->validateInvoicePayment(
                $data,
                $allocations
            ),

            PaymentTypeEnum::ADVANCE =>
            $this->validateAdvancePayment(
                $data,
                $allocations
            ),

            PaymentTypeEnum::REFUND =>
            $this->validateRefundPayment(
                $data,
                $allocations
            ),
        };
    }

    protected function validateInvoicePayment(
        array $data,
        array $allocations
    ): void {

        if (
            empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Invoice payment requires at least one allocation.'
                ]
            ]);
        }

        $totalAllocated = 0;

        foreach ($allocations as $allocation) {

            $purchase =
                Purchase::query()
                ->findOrFail(
                    $allocation['purchase_id']
                );

            if (
                $allocation['allocated_amount']
                >
                $purchase->due_amount
            ) {

                throw ValidationException::withMessages([
                    'allocated_amount' => [
                        "Allocated amount exceeds invoice due amount for {$purchase->purchase_no}."
                    ]
                ]);
            }

            $totalAllocated +=
                $allocation['allocated_amount'];
        }

        if (
            bccomp(
                (string) $totalAllocated,
                (string) $data['amount'],
                4
            ) !== 0
        ) {

            throw ValidationException::withMessages([
                'amount' => [
                    'Payment amount must equal allocated amount.'
                ]
            ]);
        }
    }

    protected function validateAdvancePayment(
        array $data,
        array $allocations
    ): void {

        if (
            !empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Advance payment cannot contain allocations.'
                ]
            ]);
        }
    }

    protected function validateRefundPayment(
        array $data,
        array $allocations
    ): void {

        if (
            !empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Refund payment cannot contain allocations.'
                ]
            ]);
        }
    }
}
