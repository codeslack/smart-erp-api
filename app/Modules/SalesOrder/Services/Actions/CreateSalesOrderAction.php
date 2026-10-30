<?php

namespace App\Modules\SalesOrder\Services\Actions;

use App\Modules\DocumentNumber\Enums\DocumentType;
use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\SalesOrder\Services\Calculators\SalesOrderCalculator;
use App\Modules\SalesOrder\Services\Calculators\SalesOrderItemCalculator;
use App\Modules\SalesOrder\Services\Support\SalesOrderPayloadResolver;
use Illuminate\Support\Facades\DB;

class CreateSalesOrderAction
{
    public function __construct(
        protected SalesOrderPayloadResolver $payloadResolver,
        protected SalesOrderItemCalculator $itemCalculator,
        protected SalesOrderCalculator $orderCalculator
    ) {}

    public function execute(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->payloadResolver->resolveHeader($data);
            $rawItems = $headerData['items'] ?? [];
            unset($headerData['items']);

            $defaultWarehouseId = $headerData['warehouse_id'] ?? null;
            $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

            $calculatedItems = [];
            foreach ($resolvedItems as $item) {
                $calculatedItems[] = $this->itemCalculator->calculate($item);
            }

            $additionalCharge = (float) ($headerData['additional_charge_amount'] ?? 0);
            $roundingAdjustment = (float) ($headerData['rounding_adjustment'] ?? 0);
            $headerDiscount = (float) ($headerData['discount_amount'] ?? 0);

            $totals = $this->orderCalculator->calculate(
                $calculatedItems,
                $additionalCharge,
                $roundingAdjustment,
                $headerDiscount
            );

            $orderNumber = $headerData['order_number']
                ?? $headerData['so_no']
                ?? nextDocumentNumber(DocumentType::SALES_ORDER);

            $orderData = array_merge($headerData, $totals, [
                'order_number' => $orderNumber,
                'status' => SalesOrderStatusEnum::DRAFT,
                'created_by' => auth()->id(),
            ]);

            unset($orderData['so_no']);

            /** @var SalesOrder $salesOrder */
            $salesOrder = SalesOrder::create($orderData);

            foreach ($calculatedItems as $item) {
                $item['sales_order_id'] = $salesOrder->id;
                SalesOrderItem::create($item);
            }

            return $salesOrder->load([
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
