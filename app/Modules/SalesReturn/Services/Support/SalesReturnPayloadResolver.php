<?php

namespace App\Modules\SalesReturn\Services\Support;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\SalesReturn\Models\SaleReturnItemBatch;

class SalesReturnPayloadResolver
{
    public function resolveHeader(array $data): array
    {
        if (isset($data['sale_uuid']) && ! isset($data['sale_id'])) {
            $data['sale_id'] = Sale::query()
                ->where('uuid', $data['sale_uuid'])
                ->value('id');
        }

        unset($data['sale_uuid']);

        if (isset($data['sale_id'])) {
            $sale = Sale::query()->find((int) $data['sale_id']);

            if ($sale) {
                // Customer and warehouse always come from the source sale.
                $data['customer_id'] = $sale->customer_id;
                $data['warehouse_id'] = $sale->warehouse_id;
            }
        }

        if (isset($data['refund_account_uuid']) && ! isset($data['refund_account_id'])) {
            $data['refund_account_id'] = ChartOfAccount::query()
                ->where('uuid', $data['refund_account_uuid'])
                ->value('id');
        }

        unset(
            $data['customer_uuid'],
            $data['warehouse_uuid'],
            $data['refund_account_uuid'],
            $data['discount_amount'],
            $data['discount'],
            $data['rounding_amount']
        );

        // Return totals are derived from the original sale lines.
        $data['rounding_amount'] = 0.0;

        return $data;
    }

    public function resolveItems(array $rawItems, ?int $defaultWarehouseId = null): array
    {
        $resolved = [];

        foreach ($rawItems as $item) {
            $saleItem = null;

            if (! empty($item['sale_item_uuid'])) {
                $saleItem = SaleItem::query()
                    ->where('uuid', $item['sale_item_uuid'])
                    ->first();
            } elseif (! empty($item['sale_item_id'])) {
                $saleItem = SaleItem::query()
                    ->find((int) $item['sale_item_id']);
            }

            $returnedQuantity = (float) (
                $item['returned_quantity']
                ?? $item['quantity']
                ?? 0
            );

            if ($saleItem) {
                $saleItem->loadMissing(['batches', 'serials']);

                $soldQuantity = (float) $saleItem->quantity;
                $soldBaseQuantity = (float) (
                    $saleItem->base_quantity
                    ?? $saleItem->quantity
                );

                $baseQuantity = $soldQuantity > 0
                    ? $soldBaseQuantity * ($returnedQuantity / $soldQuantity)
                    : 0.0;

                $discountType = $saleItem->discount_type ?? 'fixed';
                $discountAmount = $soldQuantity > 0
                    ? (float) $saleItem->discount_amount
                        * ($returnedQuantity / $soldQuantity)
                    : 0.0;

                $taxRate = (float) $saleItem->tax_rate;
                $taxAmount = $taxRate > 0 || $soldQuantity <= 0
                    ? 0.0
                    : (float) $saleItem->tax_amount
                        * ($returnedQuantity / $soldQuantity);

                $item = array_merge($item, [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'warehouse_id' => $saleItem->warehouse_id ?? $defaultWarehouseId,
                    'unit_id' => $saleItem->unit_id,
                    'line_type' => $saleItem->line_type,
                    'item_name' => $saleItem->item_name,
                    'sku' => $saleItem->sku,
                    'description' => $saleItem->description,
                    'returned_quantity' => $returnedQuantity,
                    'base_quantity' => round($baseQuantity, 4),
                    'unit_price' => (float) $saleItem->unit_price,
                    'unit_cost' => $this->resolveOriginalUnitCost($saleItem),
                    'discount_type' => $discountType,
                    'discount_rate' => (float) $saleItem->discount_rate,
                    'discount_amount' => $discountType === 'percentage'
                        ? 0.0
                        : round($discountAmount, 4),
                    'tax_id' => $saleItem->tax_id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => round($taxAmount, 4),
                ]);

                if (isset($item['batches']) && is_array($item['batches'])) {
                    $item['batches'] = array_map(
                        function (array $batch) use ($saleItem): array {
                            $sourceBatch = $saleItem->batches
                                ->firstWhere('batch_no', $batch['batch_no'] ?? null);

                            if (! $sourceBatch) {
                                return $batch;
                            }

                            $sourceQuantity = (float) $sourceBatch->quantity;
                            $sourceBaseQuantity = (float) (
                                $sourceBatch->base_quantity
                                ?? $sourceBatch->quantity
                            );
                            $returnQuantity = (float) ($batch['quantity'] ?? 0);

                            return array_merge($batch, [
                                'product_batch_id' => $sourceBatch->product_batch_id,
                                'base_quantity' => round(
                                    $sourceQuantity > 0
                                        ? $sourceBaseQuantity * ($returnQuantity / $sourceQuantity)
                                        : 0,
                                    4
                                ),
                            ]);
                        },
                        $item['batches']
                    );
                }

                if (isset($item['serials']) && is_array($item['serials'])) {
                    $item['serials'] = array_map(
                        function ($serial) use ($saleItem): array {
                            $serialNumber = is_array($serial)
                                ? (string) ($serial['serial_number'] ?? '')
                                : (string) $serial;

                            $sourceSerial = $saleItem->serials
                                ->firstWhere('serial_number', $serialNumber);

                            return [
                                'product_serial_id' => $sourceSerial?->product_serial_id,
                                'serial_number' => $sourceSerial?->serial_number ?? $serialNumber,
                            ];
                        },
                        $item['serials']
                    );
                }
            } else {
                $item['returned_quantity'] = $returnedQuantity;
            }

            unset(
                $item['sale_item_uuid'],
                $item['quantity'],
                $item['product_uuid'],
                $item['product_variant_uuid'],
                $item['warehouse_uuid'],
                $item['unit_uuid'],
                $item['tax_uuid']
            );

            $resolved[] = $item;
        }

        return $resolved;
    }

    protected function resolveOriginalUnitCost(SaleItem $saleItem): float
    {
        $costPrice = (float) $saleItem->cost_price;

        if ($costPrice > 0 || ! $saleItem->product_id) {
            return round($costPrice, 4);
        }

        $query = StockLedger::query()
            ->where('referenceable_type', Sale::class)
            ->where('referenceable_id', $saleItem->sale_id)
            ->where('product_id', $saleItem->product_id)
            ->where('warehouse_id', $saleItem->warehouse_id)
            ->where('transaction_type', InventoryTransactionTypeEnum::SALE->value)
            ->where('quantity_out', '>', 0);

        if ($saleItem->product_variant_id === null) {
            $query->whereNull('product_variant_id');
        } else {
            $query->where('product_variant_id', $saleItem->product_variant_id);
        }

        $quantityOut = (float) (clone $query)->sum('quantity_out');
        $totalCost = (float) $query->sum('total_cost');

        return $quantityOut > 0
            ? round($totalCost / $quantityOut, 4)
            : round($costPrice, 4);
    }
}
