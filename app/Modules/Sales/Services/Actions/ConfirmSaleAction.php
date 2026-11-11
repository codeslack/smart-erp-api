<?php

namespace App\Modules\Sales\Services\Actions;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\Posting\SaleAccountingPostingService;
use App\Modules\Sales\Services\Posting\SaleInventoryPostingService;
use App\Modules\Sales\Services\Validators\SaleValidator;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;

class ConfirmSaleAction
{
    public function __construct(
        protected SaleValidator $validator,
        protected SaleInventoryPostingService $inventoryPostingService,
        protected SaleAccountingPostingService $accountingPostingService
    ) {}

    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            $this->validator->validateForConfirmation($sale);

            $sale->update([
                'status' => SaleStatusEnum::CONFIRMED,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            // Post inventory movements (stock out)
            $this->inventoryPostingService->post($sale);

            // Post GL revenue/COGS and advance auto-adjustments
            $this->accountingPostingService->post($sale);

            // Update linked Sales Order progress if present
            if ($sale->sales_order_id) {
                foreach ($sale->items as $item) {
                    if ($item->sales_order_item_id) {
                        SalesOrderItem::query()
                            ->where('id', $item->sales_order_item_id)
                            ->increment('invoiced_quantity', $item->quantity);
                    }
                }
            }

            return $sale->fresh([
                'items.product',
                'items.salesOrderItem',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.batches',
                'items.serials',
                'customer',
                'warehouse',
                'paymentTerm',
                'journalEntry',
            ]);
        });
    }
}
