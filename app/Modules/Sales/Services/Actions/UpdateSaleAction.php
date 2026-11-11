<?php

namespace App\Modules\Sales\Services\Actions;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleItemBatch;
use App\Modules\Sales\Models\SaleItemSerial;
use App\Modules\Sales\Services\Calculators\SaleCalculator;
use App\Modules\Sales\Services\Calculators\SaleItemCalculator;
use App\Modules\Sales\Services\Support\SalePayloadResolver;
use App\Modules\Sales\Services\Validators\SaleValidator;
use Illuminate\Support\Facades\DB;

class UpdateSaleAction
{
    public function __construct(
        protected SalePayloadResolver $payloadResolver,
        protected SaleItemCalculator $itemCalculator,
        protected SaleCalculator $saleCalculator,
        protected SaleValidator $validator
    ) {}

    public function execute(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            $this->validator->validateForUpdate($sale);

            $headerData = $this->payloadResolver->resolveHeader($data);
            $hasItems = isset($headerData['items']) && is_array($headerData['items']);

            $existingItemDiscount = (float) $sale->items()->sum('discount_amount');
            $headerDiscount = max(
                0,
                round((float) $sale->discount_amount - $existingItemDiscount, 4)
            );

            if (array_key_exists('discount_amount', $headerData)) {
                $headerDiscount = (float) $headerData['discount_amount'];
            } elseif (array_key_exists('discount', $headerData)) {
                $headerDiscount = (float) $headerData['discount'];
            }

            if ($hasItems) {
                $rawItems = $headerData['items'];
                unset($headerData['items']);

                $defaultWarehouseId = $headerData['warehouse_id'] ?? $sale->warehouse_id;
                $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

                $calculatedItems = [];
                foreach ($resolvedItems as $item) {
                    $calculatedItems[] = $this->itemCalculator->calculate($item);
                }

                $calculationItems = $calculatedItems;

                // Sync items
                $sale->items()->delete();
                SaleItemBatch::query()->where('sale_id', $sale->id)->delete();
                SaleItemSerial::query()->where('sale_id', $sale->id)->delete();

                foreach ($calculatedItems as $item) {
                    $item['sale_id'] = $sale->id;
                    $batches = $item['batches'] ?? [];
                    $serials = $item['serials'] ?? [];
                    unset($item['batches'], $item['serials']);

                    /** @var SaleItem $saleItem */
                    $saleItem = SaleItem::create($item);

                    foreach ($batches as $batch) {
                        SaleItemBatch::create([
                            'sale_id' => $sale->id,
                            'sale_item_id' => $saleItem->id,
                            'product_batch_id' => null,
                            'batch_no' => $batch['batch_no'],
                            'quantity' => $batch['quantity'],
                            'base_quantity' => $batch['base_quantity'] ?? $batch['quantity'],
                        ]);
                    }

                    foreach ($serials as $serial) {
                        SaleItemSerial::create([
                            'sale_id' => $sale->id,
                            'sale_item_id' => $saleItem->id,
                            'product_serial_id' => null,
                            'serial_number' => is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : $serial,
                        ]);
                    }
                }
            } else {
                $calculationItems = $sale->items()
                    ->get()
                    ->map(fn (SaleItem $item) => [
                        'subtotal' => (float) $item->subtotal,
                        'discount_amount' => (float) $item->discount_amount,
                        'tax_amount' => (float) $item->tax_amount,
                    ])
                    ->all();
            }

            $calculationHeader = array_merge(
                $sale->toArray(),
                $headerData,
                ['discount_amount' => $headerDiscount]
            );

            $totals = $this->saleCalculator->calculate(
                $calculationItems,
                $calculationHeader
            );

            $headerData = array_merge($headerData, $totals);

            unset($headerData['sale_no'], $headerData['status']);
            $headerData['updated_by'] = auth()->id();

            $sale->update($headerData);

            return $sale->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.batches',
                'items.serials',
                'customer',
                'warehouse',
                'paymentTerm',
            ]);
        });
    }
}
