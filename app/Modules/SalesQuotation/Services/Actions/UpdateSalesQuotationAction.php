<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Models\SalesQuotationItem;
use App\Modules\SalesQuotation\Services\Calculators\SalesQuotationCalculator;
use App\Modules\SalesQuotation\Services\Support\SalesQuotationPayloadResolver;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;
use Illuminate\Support\Facades\DB;

class UpdateSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationValidator $validator,
        protected SalesQuotationPayloadResolver $payloadResolver,
        protected SalesQuotationCalculator $calculator
    ) {}

    public function execute(SalesQuotation $quotation, array $data): SalesQuotation
    {
        $this->validator->validateForUpdate($quotation);

        return DB::transaction(function () use ($quotation, $data) {
            $headerData = $this->payloadResolver->resolveHeader($data);

            if (isset($headerData['items'])) {
                $rawItems = $headerData['items'];
                $defaultWarehouseId = $headerData['warehouse_id'] ?? $quotation->warehouse_id;
                $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

                $headerData['items'] = $resolvedItems;
                $calculatedData = $this->calculator->calculate($headerData);

                $itemsToSave = $calculatedData['items'] ?? [];
                unset($calculatedData['items']);

                // Delete existing items and recreate
                $quotation->items()->delete();

                foreach ($itemsToSave as $itemData) {
                    SalesQuotationItem::query()->create([
                        ...$itemData,
                        'sales_quotation_id' => $quotation->id,
                    ]);
                }

                $quotation->update([
                    ...$calculatedData,
                    'updated_by' => auth()->id(),
                ]);
            } else {
                $quotation->update([
                    ...$headerData,
                    'updated_by' => auth()->id(),
                ]);
            }

            return $quotation->fresh([
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'customer',
                'warehouse',
            ]);
        });
    }
}
