<?php

namespace App\Modules\SalesReturn\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;

class SalesReturnDueAmountService
{
    public function recalculate(Sale $sale): void
    {
        $creditedAmount = (float) SalesReturn::query()
            ->where('sale_id', $sale->id)
            ->where('status', SalesReturnStatus::CONFIRMED->value)
            ->where('refund_type', SalesReturnRefundType::CREDIT_NOTE->value)
            ->sum('credited_amount');

        $dueAmount = max(
            0,
            round(
                (float) $sale->grand_total
                    - (float) $sale->paid_amount
                    - $creditedAmount,
                4
            )
        );

        $sale->update([
            'due_amount' => $dueAmount,
        ]);
    }
}
