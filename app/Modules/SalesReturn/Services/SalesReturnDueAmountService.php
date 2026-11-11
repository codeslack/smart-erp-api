<?php

namespace App\Modules\SalesReturn\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;

class SalesReturnDueAmountService
{
    public function allocateRefundAmounts(
        SalesReturn $salesReturn,
        Sale $sale
    ): void {
        $refundType = $salesReturn->refund_type;

        if ($refundType === SalesReturnRefundType::CREDIT_NOTE) {
            $salesReturn->update([
                'refund_amount' => '0.0000',
                'credited_amount' => $this->decimal($salesReturn->grand_total),
            ]);

            return;
        }

        $previousCashRefunds = SalesReturn::query()
            ->where('sale_id', $sale->id)
            ->where('id', '!=', $salesReturn->id)
            ->where('status', SalesReturnStatus::CONFIRMED->value)
            ->whereIn('refund_type', [
                SalesReturnRefundType::CASH_REFUND->value,
                SalesReturnRefundType::BANK_REFUND->value,
            ])
            ->sum('refund_amount');

        $availablePaidAmount = bcsub(
            $this->decimal($sale->paid_amount),
            $this->decimal($previousCashRefunds),
            4
        );

        if (bccomp($availablePaidAmount, '0.0000', 4) < 0) {
            $availablePaidAmount = '0.0000';
        }

        $grandTotal = $this->decimal($salesReturn->grand_total);
        $refundAmount = bccomp($grandTotal, $availablePaidAmount, 4) <= 0
            ? $grandTotal
            : $availablePaidAmount;

        $salesReturn->update([
            'refund_amount' => $refundAmount,
            'credited_amount' => bcsub($grandTotal, $refundAmount, 4),
        ]);
    }

    public function recalculate(Sale $sale): void
    {
        $creditedAmount = $this->decimal(
            SalesReturn::query()
                ->where('sale_id', $sale->id)
                ->where('status', SalesReturnStatus::CONFIRMED->value)
                ->sum('credited_amount')
        );

        $dueAmount = bcsub(
            bcsub(
                $this->decimal($sale->grand_total),
                $this->decimal($sale->paid_amount),
                4
            ),
            $creditedAmount,
            4
        );

        if (bccomp($dueAmount, '0.0000', 4) < 0) {
            $dueAmount = '0.0000';
        }

        $sale->update([
            'due_amount' => $dueAmount,
        ]);
    }

    private function decimal(string|int|float|null $amount): string
    {
        return bcadd((string) ($amount ?? 0), '0', 4);
    }
}
