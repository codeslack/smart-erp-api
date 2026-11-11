<?php

namespace App\Modules\SalesReturn\Services\Actions;

use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Services\Posting\SalesReturnAccountingPostingService;
use App\Modules\SalesReturn\Services\Posting\SalesReturnInventoryPostingService;
use App\Modules\SalesReturn\Services\Validators\SalesReturnValidator;
use Illuminate\Support\Facades\DB;

class CancelSalesReturnAction
{
    public function __construct(
        protected SalesReturnValidator $validator,
        protected SalesReturnInventoryPostingService $inventoryPostingService,
        protected SalesReturnAccountingPostingService $accountingPostingService
    ) {}

    public function execute(SalesReturn $salesReturn, ?string $reason = null): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $reason) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            $this->validator->validateForCancellation($salesReturn);

            $sale = $salesReturn->sale_id
                ? Sale::query()->lockForUpdate()->find($salesReturn->sale_id)
                : null;

            $wasConfirmed = $salesReturn->status === SalesReturnStatus::CONFIRMED;

            if ($wasConfirmed) {
                // Reverse inventory restock
                $this->inventoryPostingService->reverse($salesReturn);

                // Reverse GL journal entries
                $this->accountingPostingService->reverse($salesReturn);

                // Revert referenced Sale due amount
                if ($sale) {
                    $newDue = round((float) $sale->due_amount + (float) $salesReturn->grand_total, 4);
                    $sale->update([
                        'due_amount' => $newDue,
                    ]);
                }
            }

            $salesReturn->update([
                'status' => SalesReturnStatus::CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $salesReturn->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'customer',
                'sale',
            ]);
        });
    }
}
