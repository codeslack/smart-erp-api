<?php

namespace App\Modules\Sales\Services\Actions;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\Posting\SaleAccountingPostingService;
use App\Modules\Sales\Services\Posting\SaleInventoryPostingService;
use App\Modules\Sales\Services\Validators\SaleValidator;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            // Lock source order lines in a stable order to prevent concurrent over-invoicing.
            $linkedItems = $sale->items()
                ->whereNotNull('sales_order_item_id')
                ->get();

            if ($linkedItems->isNotEmpty() && ! $sale->sales_order_id) {
                throw ValidationException::withMessages([
                    'sales_order_id' => ['A linked sales order is required for invoicing these order lines.'],
                ]);
            }

            $quantities = $linkedItems
                ->groupBy('sales_order_item_id')
                ->map(fn ($items) => (float) $items->sum('quantity'));

            $orderItems = SalesOrderItem::query()
                ->whereIn('id', $quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantities as $orderItemId => $invoiceQuantity) {
                $orderItem = $orderItems->get($orderItemId);

                if (! $orderItem || (int) $orderItem->sales_order_id !== (int) $sale->sales_order_id) {
                    throw ValidationException::withMessages([
                        'items' => ['A linked sales order item no longer belongs to this sales order.'],
                    ]);
                }

                $remainingQuantity = (float) $orderItem->quantity
                    - (float) $orderItem->invoiced_quantity
                    - (float) $orderItem->cancelled_quantity;

                if ($invoiceQuantity > $remainingQuantity + 0.00001) {
                    throw ValidationException::withMessages([
                        'items' => ["The invoice quantity exceeds the remaining quantity for sales order item {$orderItem->uuid}."],
                    ]);
                }
            }

            foreach ($quantities as $orderItemId => $invoiceQuantity) {
                $orderItems->get($orderItemId)->increment('invoiced_quantity', $invoiceQuantity);
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
