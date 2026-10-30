<?php

namespace App\Modules\SalesOrder\Services\Actions;

use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\SalesOrder\Services\Calculators\SalesOrderCalculator;
use App\Modules\SalesOrder\Services\Calculators\SalesOrderItemCalculator;
use App\Modules\SalesOrder\Services\Support\SalesOrderPayloadResolver;
use App\Modules\SalesOrder\Services\Validators\SalesOrderValidator;
use Illuminate\Support\Facades\DB;

class UpdateSalesOrderAction
{
    public function __construct(
        protected SalesOrderPayloadResolver $payloadResolver,
        protected SalesOrderItemCalculator $itemCalculator,
        protected SalesOrderCalculator $orderCalculator,
        protected SalesOrderValidator $validator
    ) {}

    public function execute(SalesOrder $salesOrder, array $data): SalesOrder
    {
        $this->validator->validateForUpdate($salesOrder);

        return DB::transaction(function () use ($salesOrder, $data) {
            $headerData = $this->payloadResolver->resolveHeader($data);
            $hasItems = isset($headerData['items']) && is_array($headerData['items']);

            if ($hasItems) {
                $rawItems = $headerData['items'];
                unset($headerData['items']);

                $defaultWarehouseId = $headerData['warehouse_id'] ?? $salesOrder->warehouse_id;
                $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

                $calculatedItems = [];
                foreach ($resolvedItems as $item) {
                    $calculatedItems[] = $this->itemCalculator->calculate($item);
                }

                $additionalCharge = (float) ($headerData['additional_charge_amount'] ?? $salesOrder->additional_charge_amount);
                $roundingAdjustment = (float) ($headerData['rounding_adjustment'] ?? $salesOrder->rounding_adjustment);
                $headerDiscount = (float) ($headerData['discount_amount'] ?? $salesOrder->discount_amount);

                $totals = $this->orderCalculator->calculate(
                    $calculatedItems,
                    $additionalCharge,
                    $roundingAdjustment,
                    $headerDiscount
                );

                $headerData = array_merge($headerData, $totals);

                // Sync items: delete old items and create updated items
                $salesOrder->items()->delete();
                foreach ($calculatedItems as $item) {
                    $item['sales_order_id'] = $salesOrder->id;
                    SalesOrderItem::create($item);
                }
            }

            unset($headerData['order_number'], $headerData['so_no'], $headerData['status']);
            $headerData['updated_by'] = auth()->id();

            $salesOrder->update($headerData);

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
