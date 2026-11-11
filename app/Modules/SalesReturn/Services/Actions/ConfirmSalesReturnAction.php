<?php

namespace App\Modules\SalesReturn\Services\Actions;

use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Services\Posting\SalesReturnAccountingPostingService;
use App\Modules\SalesReturn\Services\Posting\SalesReturnInventoryPostingService;
use App\Modules\SalesReturn\Services\Validators\SalesReturnValidator;
use Illuminate\Support\Facades\DB;

class ConfirmSalesReturnAction
{
    public function __construct(
        protected SalesReturnValidator $validator,
        protected SalesReturnInventoryPostingService $inventoryPostingService,
        protected SalesReturnAccountingPostingService $accountingPostingService
    ) {}

    public function execute(SalesReturn $salesReturn): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            $sale = Sale::query()
                ->lockForUpdate()
                ->find($salesReturn->sale_id);

            $this->validator->validateForConfirmation($salesReturn);

            $salesReturn->update([
                'status' => SalesReturnStatus::CONFIRMED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Restock items in inventory
            $this->inventoryPostingService->post($salesReturn);

            // Post GL entries (revenue reversal, COGS restoration)
            $this->accountingPostingService->post($salesReturn);

            // Adjust referenced Sale due amount
            if ($sale) {
                $newDue = max(0, round((float) $sale->due_amount - (float) $salesReturn->grand_total, 4));
                $sale->update([
                    'due_amount' => $newDue,
                ]);
            }

            return $salesReturn->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.batches',
                'items.serials',
                'customer',
                'sale',
                'refundAccount',
                'journalEntry',
            ]);
        });
    }
}
