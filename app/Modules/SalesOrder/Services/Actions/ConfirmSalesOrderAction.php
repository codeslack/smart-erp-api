<?php

namespace App\Modules\SalesOrder\Services\Actions;

use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Services\Validators\SalesOrderValidator;
use Illuminate\Support\Facades\DB;

class ConfirmSalesOrderAction
{
    public function __construct(
        protected SalesOrderValidator $validator
    ) {}

    public function execute(SalesOrder $salesOrder): SalesOrder
    {
        $this->validator->validateForConfirmation($salesOrder);

        return DB::transaction(function () use ($salesOrder) {
            $salesOrder->update([
                'status' => SalesOrderStatusEnum::CONFIRMED,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
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
