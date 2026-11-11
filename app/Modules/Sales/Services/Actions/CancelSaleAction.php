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

                // Reverse invoiced quantities from the exact linked source order lines.
                $linkedItems = $sale->items()
                    ->whereNotNull('sales_order_item_id')
                    ->get();

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

                    if (
                        ! $orderItem
                        || (int) $orderItem->sales_order_id !== (int) $sale->sales_order_id
                        || (float) $orderItem->invoiced_quantity + 0.00001 < $invoiceQuantity
                    ) {
                        throw ValidationException::withMessages([
                            'items' => ['The linked sales order invoiced quantity cannot be safely reversed.'],
                        ]);
                    }
                }

                foreach ($quantities as $orderItemId => $invoiceQuantity) {
                    $orderItems->get($orderItemId)->decrement('invoiced_quantity', $invoiceQuantity);
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
                'items.salesOrderItem',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'customer',
                'warehouse',
            ]);
        });
    }
}
