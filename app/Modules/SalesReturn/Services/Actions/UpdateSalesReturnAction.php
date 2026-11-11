<?php

namespace App\Modules\SalesReturn\Services\Actions;

use App\Modules\SalesReturn\Models\SaleReturnItemBatch;
use App\Modules\SalesReturn\Models\SaleReturnItemSerial;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use App\Modules\SalesReturn\Services\Calculators\SalesReturnCalculator;
use App\Modules\SalesReturn\Services\Calculators\SalesReturnItemCalculator;
use App\Modules\SalesReturn\Services\Support\SalesReturnPayloadResolver;
use App\Modules\SalesReturn\Services\Validators\SalesReturnValidator;
use Illuminate\Support\Facades\DB;

class UpdateSalesReturnAction
{
    public function __construct(
        protected SalesReturnPayloadResolver $payloadResolver,
        protected SalesReturnItemCalculator $itemCalculator,
        protected SalesReturnCalculator $calculator,
        protected SalesReturnValidator $validator
    ) {}

    public function execute(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            $this->validator->validateForUpdate($salesReturn);

            $headerInput = $data;
            $sourceSaleProvided = isset($data['sale_id']) || isset($data['sale_uuid']);

            if (! $sourceSaleProvided) {
                $headerInput['sale_id'] = $salesReturn->sale_id;

                if (! isset($data['customer_id']) && ! isset($data['customer_uuid'])) {
                    $headerInput['customer_id'] = $salesReturn->customer_id;
                }
            }

            $headerData = $this->payloadResolver->resolveHeader($headerInput);
            $this->validator->validateRefundDetails(
                array_merge($salesReturn->toArray(), $headerData)
            );

            $hasItems = isset($headerData['items']) && is_array($headerData['items']);

            if ($hasItems) {
                $rawItems = $headerData['items'];
                unset($headerData['items']);

                $defaultWarehouseId = $headerData['warehouse_id'] ?? $salesReturn->warehouse_id;
                $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

                $this->validator->validateForSave(
                    array_merge(['sale_id' => $salesReturn->sale_id], $headerData),
                    $resolvedItems,
                    $salesReturn->id
                );

                $calculatedItems = [];
                foreach ($resolvedItems as $item) {
                    $calculatedItems[] = $this->itemCalculator->calculate($item);
                }

                $headerData = $this->payloadResolver->applySourceHeaderDiscount(
                    array_merge(['sale_id' => $salesReturn->sale_id], $headerData),
                    $calculatedItems
                );

                $totals = $this->calculateTotals(
                    $calculatedItems,
                    $salesReturn,
                    $headerData
                );
                $headerData = array_merge($headerData, $totals);

                // Sync items
                $salesReturn->items()->delete();
                SaleReturnItemBatch::query()->where('sales_return_id', $salesReturn->id)->delete();
                SaleReturnItemSerial::query()->where('sales_return_id', $salesReturn->id)->delete();

                foreach ($calculatedItems as $item) {
                    $item['sales_return_id'] = $salesReturn->id;
                    $batches = $item['batches'] ?? [];
                    $serials = $item['serials'] ?? [];
                    unset($item['batches'], $item['serials']);

                    /** @var SalesReturnItem $returnItem */
                    $returnItem = SalesReturnItem::create($item);

                    foreach ($batches as $batch) {
                        SaleReturnItemBatch::create([
                            'sales_return_id' => $salesReturn->id,
                            'sales_return_item_id' => $returnItem->id,
                            'product_batch_id' => $batch['product_batch_id'],
                            'batch_no' => $batch['batch_no'],
                            'quantity' => $batch['quantity'],
                        ]);
                    }

                    foreach ($serials as $serial) {
                        SaleReturnItemSerial::create([
                            'sales_return_id' => $salesReturn->id,
                            'sales_return_item_id' => $returnItem->id,
                            'product_serial_id' => null,
                            'serial_number' => is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : $serial,
                        ]);
                    }
                }
            }

            if (
                ! $hasItems
                && array_intersect(
                    array_keys($headerData),
                    ['refund_type', 'discount_amount', 'discount', 'rounding_amount']
                ) !== []
            ) {
                $existingItems = $salesReturn->items()
                    ->get()
                    ->map(fn ($item) => $item->toArray())
                    ->all();

                $totals = $this->calculateTotals(
                    $existingItems,
                    $salesReturn,
                    $headerData
                );

                $headerData = array_merge($headerData, $totals);
            }

            unset($headerData['return_no'], $headerData['status']);
            $headerData['updated_by'] = auth()->id();

            $salesReturn->update($headerData);

            return $salesReturn->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.batches',
                'items.serials',
                'customer',
                'warehouse',
                'sale',
                'refundAccount',
            ]);
        });
    }

    private function calculateTotals(
        array $items,
        SalesReturn $salesReturn,
        array $headerData
    ): array {
        $headerForCalculation = array_merge(
            $salesReturn->toArray(),
            $headerData
        );

        if (array_key_exists('discount_amount', $headerData)) {
            $headerForCalculation['discount_amount'] = $headerData['discount_amount'];
        } elseif (array_key_exists('discount', $headerData)) {
            $headerForCalculation['discount_amount'] = $headerData['discount'];
        } else {
            $itemDiscounts = array_sum(array_map(
                fn (array $item) => (float) ($item['discount_amount'] ?? 0),
                $items
            ));

            $headerForCalculation['discount_amount'] = max(
                0,
                (float) $salesReturn->discount_amount - $itemDiscounts
            );
        }

        return $this->calculator->calculate(
            $items,
            $headerForCalculation
        );
    }
}
