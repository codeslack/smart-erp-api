<?php

namespace App\Modules\Sales\Services\Actions;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\Posting\SaleAccountingPostingService;
use App\Modules\Sales\Services\Posting\SaleInventoryPostingService;
use App\Modules\Sales\Services\Validators\SaleValidator;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;

class CancelSaleAction
{
    public function __construct(
        protected SaleValidator $validator,
        protected SaleInventoryPostingService $inventoryPostingService,
        protected SaleAccountingPostingService $accountingPostingService
    ) {}

    public function execute(Sale $sale, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            $this->validator->validateForCancellation($sale);

            $wasConfirmed = $sale->status === SaleStatusEnum::CONFIRMED;

            if ($wasConfirmed) {
                // Reverse inventory movements
                $this->inventoryPostingService->reverse($sale);

                // Reverse accounting postings
                $this->accountingPostingService->reverse($sale);

                // Reverse linked sales order progress if present
                if ($sale->sales_order_id) {
                    foreach ($sale->items as $item) {
                        if ($item->sales_order_item_id) {
                            SalesOrderItem::query()
                                ->where('id', $item->sales_order_item_id)
                                ->decrement('invoiced_quantity', $item->quantity);
                        }
                    }
                }
            }

            $sale->update([
                'status' => SaleStatusEnum::CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $sale->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'customer',
                'warehouse',
            ]);
        });
    }
}
