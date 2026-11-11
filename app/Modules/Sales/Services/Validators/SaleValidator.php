<?php

namespace App\Modules\Sales\Services\Validators;

use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Sales\Models\Sale;
use Illuminate\Validation\ValidationException;

class SaleValidator
{
    public function validateForUpdate(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be updated.'],
            ]);
        }
    }

    public function validateForConfirmation(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be confirmed.'],
            ]);
        }

        if ($sale->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Cannot confirm a sale without items.'],
            ]);
        }
    }

    public function validateForCancellation(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if (! in_array($status, [SaleStatusEnum::DRAFT, SaleStatusEnum::CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sale that is in ' . ($status?->label() ?? 'current') . ' status.'],
            ]);
        }

        $hasConfirmedReturns = SalesReturn::query()
            ->where('sale_id', $sale->id)
            ->where('status', SalesReturnStatus::CONFIRMED->value)
            ->exists();

        if ($hasConfirmedReturns) {
            throw ValidationException::withMessages([
                'sales_returns' => [
                    'Cancel all confirmed Sales Returns before cancelling this Sale.',
                ],
            ]);
        }

        $hasConfirmedPayments = PaymentAllocation::query()
            ->where('document_type', 'sales_invoice')
            ->where('document_id', $sale->id)
            ->whereHas('payment', fn ($query) => $query->where(
                'status',
                PaymentStatusEnum::CONFIRMED->value
            ))
            ->exists();

        if ($hasConfirmedPayments) {
            throw ValidationException::withMessages([
                'payment_allocations' => [
                    'Reverse confirmed customer receipts allocated to this Sale before cancelling it.',
                ],
            ]);
        }

        $advanceAllocatedAmount = (float) AdvanceAllocation::query()
            ->where('target_type', Sale::class)
            ->where('target_id', $sale->id)
            ->sum('allocated_amount');

        $advanceAllocatedAmount = number_format(
            $advanceAllocatedAmount,
            4,
            '.',
            ''
        );

        if (
            bccomp(
                (string) $sale->paid_amount,
                $advanceAllocatedAmount,
                4
            ) !== 0
        ) {
            throw ValidationException::withMessages([
                'paid_amount' => [
                    'Reverse customer payments before cancelling this Sale. Only customer advance allocations can be released automatically.',
                ],
            ]);
        }
    }

    public function validateForDeletion(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be deleted.'],
            ]);
        }
    }
}
