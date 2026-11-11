<?php

namespace App\Modules\SalesOrder\Services\Actions;

use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Services\Validators\SalesOrderValidator;
use Illuminate\Support\Facades\DB;

class CancelSalesOrderAction
{
    public function __construct(
        protected SalesOrderValidator $validator
    ) {}

    public function execute(SalesOrder $salesOrder, ?string $reason = null): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $reason) {
            $salesOrder = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            $this->validator->validateForCancellation($salesOrder);
            $salesOrder->update([
                'status' => SalesOrderStatusEnum::CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $salesOrder->fresh([
                'items.product',
                'items.warehouse',
                'items.unit',
                'customer',
                'warehouse',
                'paymentTerm',
            ]);
        });
    }
}
