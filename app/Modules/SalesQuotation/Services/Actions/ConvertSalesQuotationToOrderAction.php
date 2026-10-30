<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Services\Actions\CreateSalesOrderAction;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;
use Illuminate\Support\Facades\DB;

class ConvertSalesQuotationToOrderAction
{
    public function __construct(
        protected SalesQuotationValidator $validator,
        protected CreateSalesOrderAction $createSalesOrderAction
    ) {}

    public function execute(SalesQuotation $quotation, array $overrides = []): SalesOrder
    {
        $this->validator->validateForConversion($quotation);

        return DB::transaction(function () use ($quotation, $overrides) {
            $quotation->loadMissing(['items', 'customer', 'warehouse']);

            $orderItems = [];
            foreach ($quotation->items as $item) {
                $orderItems[] = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $item->warehouse_id ?? $quotation->warehouse_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_rate' => (float) $item->discount_rate,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_id' => $item->tax_id,
                    'tax_rate' => (float) $item->tax_rate,
                    'tax_amount' => (float) $item->tax_amount,
                ];
            }

            $orderPayload = array_merge([
                'customer_id' => $quotation->customer_id,
                'warehouse_id' => $quotation->warehouse_id,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $quotation->expiry_date?->toDateString(),
                'sales_quotation_id' => $quotation->id,
                'currency_code' => $quotation->currency_code ?? 'USD',
                'exchange_rate' => (float) ($quotation->exchange_rate ?? 1.0),
                'notes' => $quotation->notes,
                'terms_and_conditions' => $quotation->terms_conditions,
                'items' => $orderItems,
            ], $overrides);

            $salesOrder = $this->createSalesOrderAction->execute($orderPayload);

            $quotation->update([
                'status' => SalesQuotationStatusEnum::CONVERTED_TO_ORDER->value,
                'converted_sales_order_id' => $salesOrder->id,
                'updated_by' => auth()->id(),
            ]);

            return $salesOrder;
        });
    }
}
