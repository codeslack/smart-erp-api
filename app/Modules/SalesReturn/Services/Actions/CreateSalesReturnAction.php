<?php

namespace App\Modules\SalesReturn\Services\Actions;

use App\Modules\DocumentNumber\Enums\DocumentType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SaleReturnItemBatch;
use App\Modules\SalesReturn\Models\SaleReturnItemSerial;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use App\Modules\SalesReturn\Services\Calculators\SalesReturnCalculator;
use App\Modules\SalesReturn\Services\Calculators\SalesReturnItemCalculator;
use App\Modules\SalesReturn\Services\Support\SalesReturnPayloadResolver;
use App\Modules\SalesReturn\Services\Validators\SalesReturnValidator;
use Illuminate\Support\Facades\DB;

class CreateSalesReturnAction
{
    public function __construct(
        protected SalesReturnPayloadResolver $payloadResolver,
        protected SalesReturnItemCalculator $itemCalculator,
        protected SalesReturnCalculator $calculator,
        protected SalesReturnValidator $validator
    ) {}

    public function execute(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->payloadResolver->resolveHeader($data);
            $rawItems = $headerData['items'] ?? [];
            unset($headerData['items']);

            $defaultWarehouseId = $headerData['warehouse_id'] ?? null;
            $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

            $this->validator->validateForSave($headerData, $resolvedItems);

            $calculatedItems = [];
            foreach ($resolvedItems as $item) {
                $calculatedItems[] = $this->itemCalculator->calculate($item);
            }

            $totals = $this->calculator->calculate($calculatedItems, $headerData);

            $returnNo = $headerData['return_no'] ?? nextDocumentNumber(DocumentType::SALES_RETURN);

            $returnData = array_merge($headerData, $totals, [
                'return_no' => $returnNo,
                'status' => SalesReturnStatus::DRAFT,
                'created_by' => auth()->id(),
            ]);

            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::create($returnData);

            foreach ($calculatedItems as $item) {
                $item['sales_return_id'] = $salesReturn->id;

                $batches = $item['batches'] ?? [];
                $serials = $item['serials'] ?? [];
                unset($item['batches'], $item['serials']);

                /** @var SalesReturnItem $returnItem */
                $returnItem = SalesReturnItem::create($item);

                if (! empty($batches)) {
                    foreach ($batches as $batch) {
                        SaleReturnItemBatch::create([
                            'sales_return_id' => $salesReturn->id,
                            'sales_return_item_id' => $returnItem->id,
                            'product_batch_id' => $batch['product_batch_id'] ?? null,
                            'batch_no' => $batch['batch_no'],
                            'quantity' => $batch['quantity'],
                        ]);
                    }
                }

                if (! empty($serials)) {
                    foreach ($serials as $serial) {
                        SaleReturnItemSerial::create([
                            'sales_return_id' => $salesReturn->id,
                            'sales_return_item_id' => $returnItem->id,
                            'product_serial_id' => $serial['product_serial_id'] ?? null,
                            'serial_number' => is_array($serial) ? ($serial['serial_number'] ?? '') : $serial,
                        ]);
                    }
                }
            }

            return $salesReturn->load([
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
}
