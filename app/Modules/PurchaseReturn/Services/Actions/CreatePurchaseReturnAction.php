<?php

namespace App\Modules\PurchaseReturn\Services\Actions;

use App\Modules\DocumentNumber\Enums\DocumentType;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use App\Modules\PurchaseReturn\Services\Calculators\PurchaseReturnCalculator;
use App\Modules\PurchaseReturn\Services\Support\PurchaseReturnPayloadResolver;
use App\Modules\PurchaseReturn\Services\Validators\PurchaseReturnValidator;
use Illuminate\Support\Facades\DB;

class CreatePurchaseReturnAction
{
    public function __construct(
        protected PurchaseReturnRepositoryInterface $repository,
        protected PurchaseReturnPayloadResolver $resolver,
        protected PurchaseReturnValidator $validator,
        protected PurchaseReturnCalculator $calculator,
    ) {}

    public function execute(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $resolvedHeader = $this->resolver->resolveHeader($data);
            $rawItems = $resolvedHeader['items'] ?? [];
            unset($resolvedHeader['items']);

            $defaultWarehouseId = isset($resolvedHeader['purchase_id'])
                ? Purchase::query()->where('id', $resolvedHeader['purchase_id'])->value('warehouse_id')
                : null;

            $resolvedItems = $this->resolver->resolveItems($rawItems, $defaultWarehouseId);

            $this->validator->validateForSave($resolvedHeader, $resolvedItems);

            $calculatedItems = [];
            foreach ($resolvedItems as $item) {
                $calculated = $this->calculator->calculateItem($item);
                if (isset($item['batches'])) {
                    $calculated['batches'] = $item['batches'];
                }
                if (isset($item['serials'])) {
                    $calculated['serials'] = $item['serials'];
                }
                $calculatedItems[] = $calculated;
            }

            $totals = $this->calculator->calculateTotals($calculatedItems, $resolvedHeader);

            $returnNo = $resolvedHeader['return_no'] ?? nextDocumentNumber(DocumentType::PURCHASE_RETURN);

            /** @var PurchaseReturn $purchaseReturn */
            $purchaseReturn = $this->repository->create([
                ...$resolvedHeader,
                'return_no' => $returnNo,
                'status' => PurchaseReturnStatus::DRAFT->value,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'rounding_amount' => $totals['rounding_amount'],
                'grand_total' => $totals['grand_total'],
                'refund_amount' => $totals['refund_amount'],
                'credited_amount' => $totals['credited_amount'],
                'created_by' => userId(),
            ]);

            $this->saveItems($purchaseReturn, $calculatedItems);

            return $this->repository->find($purchaseReturn->id);
        });
    }

    protected function saveItems(PurchaseReturn $purchaseReturn, array $calculatedItems): void
    {
        foreach ($calculatedItems as $itemData) {
            $batches = $itemData['batches'] ?? [];
            $serials = $itemData['serials'] ?? [];
            unset($itemData['batches'], $itemData['serials']);

            /** @var PurchaseReturnItem $returnItem */
            $returnItem = PurchaseReturnItem::create([
                ...$itemData,
                'purchase_return_id' => $purchaseReturn->id,
            ]);

            foreach ($batches as $batch) {
                $batchId = ProductBatch::query()
                    ->where('batch_no', $batch['batch_no'])
                    ->where('product_id', $returnItem->product_id)
                    ->where('warehouse_id', $returnItem->warehouse_id)
                    ->value('id');

                $returnItem->batches()->create([
                    'tenant_id' => $purchaseReturn->tenant_id,
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_batch_id' => $batchId,
                    'batch_no' => $batch['batch_no'],
                    'quantity' => $batch['quantity'],
                ]);
            }

            foreach ($serials as $serial) {
                $serialNo = is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : (string) $serial;
                $serialNo = trim($serialNo);
                $serialId = ProductSerial::query()
                    ->where('serial_number', $serialNo)
                    ->where('product_id', $returnItem->product_id)
                    ->value('id');

                $returnItem->serials()->create([
                    'tenant_id' => $purchaseReturn->tenant_id,
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_serial_id' => $serialId,
                    'serial_number' => $serialNo,
                ]);
            }
        }
    }
}
