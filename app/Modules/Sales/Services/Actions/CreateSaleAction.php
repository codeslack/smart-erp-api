<?php

namespace App\Modules\Sales\Services\Actions;

use App\Modules\DocumentNumber\Enums\DocumentType;
use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleItemBatch;
use App\Modules\Sales\Models\SaleItemSerial;
use App\Modules\Sales\Services\Calculators\SaleCalculator;
use App\Modules\Sales\Services\Calculators\SaleItemCalculator;
use App\Modules\Sales\Services\Support\SalePayloadResolver;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    public function __construct(
        protected SalePayloadResolver $payloadResolver,
        protected SaleItemCalculator $itemCalculator,
        protected SaleCalculator $saleCalculator
    ) {}

    public function execute(array $data): Sale
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

            $totals = $this->saleCalculator->calculate($calculatedItems, $headerData);

            $saleNo = $headerData['sale_no'] ?? nextDocumentNumber(DocumentType::SALE_INVOICE);

            $saleData = array_merge($headerData, $totals, [
                'sale_no' => $saleNo,
                'status' => SaleStatusEnum::DRAFT,
                'created_by' => auth()->id(),
            ]);

            /** @var Sale $sale */
            $sale = Sale::create($saleData);

            foreach ($calculatedItems as $item) {
                $item['sale_id'] = $sale->id;

                $batches = $item['batches'] ?? [];
                $serials = $item['serials'] ?? [];
                unset($item['batches'], $item['serials']);

                /** @var SaleItem $saleItem */
                $saleItem = SaleItem::create($item);

                if (! empty($batches)) {
                    foreach ($batches as $batch) {
                        SaleItemBatch::create([
                            'sale_id' => $sale->id,
                            'sale_item_id' => $saleItem->id,
                            'product_batch_id' => $batch['product_batch_id'] ?? null,
                            'batch_no' => $batch['batch_no'],
                            'quantity' => $batch['quantity'],
                        ]);
                    }
                }

                if (! empty($serials)) {
                    foreach ($serials as $serial) {
                        SaleItemSerial::create([
                            'sale_id' => $sale->id,
                            'sale_item_id' => $saleItem->id,
                            'product_serial_id' => $serial['product_serial_id'] ?? null,
                            'serial_number' => is_array($serial) ? ($serial['serial_number'] ?? '') : $serial,
                        ]);
                    }
                }
            }

            return $sale->load([
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
