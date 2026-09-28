<?php

namespace App\Modules\Receipt\Validation;

use App\Modules\Sales\Models\Sale;

use App\Modules\Receipt\Enums\ReceiptTypeEnum;

use Illuminate\Validation\ValidationException;

class ReceiptValidator
{
    public function validate(
        array $data,
        array $allocations
    ): void {

        $type =
            ReceiptTypeEnum::from(
                $data['receipt_type']
            );

        match ($type) {

            ReceiptTypeEnum::INVOICE =>
            $this->validateInvoiceReceipt(
                $data,
                $allocations
            ),

            ReceiptTypeEnum::ADVANCE =>
            $this->validateAdvanceReceipt(
                $data,
                $allocations
            ),

            ReceiptTypeEnum::REFUND =>
            $this->validateRefundReceipt(
                $data,
                $allocations
            ),
        };
    }

    protected function validateInvoiceReceipt(
        array $data,
        array $allocations
    ): void {

        if (
            empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Invoice receipt requires at least one allocation.'
                ]
            ]);
        }

        $totalAllocated = 0;

        foreach ($allocations as $allocation) {

            $sale =
                Sale::query()
                ->findOrFail(
                    $allocation['sale_id']
                );

            if (
                $allocation['allocated_amount']
                >
                $sale->due_amount
            ) {

                throw ValidationException::withMessages([
                    'allocated_amount' => [
                        "Allocated amount exceeds invoice due amount for {$sale->invoice_no}."
                    ]
                ]);
            }

            $totalAllocated +=
                $allocation['allocated_amount'];
        }

        if (
            $totalAllocated >
            $data['amount']
        ) {

            throw ValidationException::withMessages([
                'amount' => [
                    'Allocated amount cannot exceed receipt amount.'
                ]
            ]);
        }
    }

    protected function validateAdvanceReceipt(
        array $data,
        array $allocations
    ): void {

        if (
            !empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Advance receipt cannot contain allocations.'
                ]
            ]);
        }
    }

    protected function validateRefundReceipt(
        array $data,
        array $allocations
    ): void {

        if (
            !empty($allocations)
        ) {

            throw ValidationException::withMessages([
                'allocations' => [
                    'Refund receipt cannot contain allocations.'
                ]
            ]);
        }
    }
}
