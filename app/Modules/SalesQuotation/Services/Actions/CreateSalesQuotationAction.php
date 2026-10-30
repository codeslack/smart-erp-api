<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\DocumentNumber\Enums\DocumentType;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Models\SalesQuotationItem;
use App\Modules\SalesQuotation\Services\Calculators\SalesQuotationCalculator;
use App\Modules\SalesQuotation\Services\Support\SalesQuotationPayloadResolver;
use Illuminate\Support\Facades\DB;

class CreateSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationPayloadResolver $payloadResolver,
        protected SalesQuotationCalculator $calculator
    ) {}

    public function execute(array $data): SalesQuotation
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->payloadResolver->resolveHeader($data);
            $rawItems = $headerData['items'] ?? [];

            $defaultWarehouseId = $headerData['warehouse_id'] ?? null;
            $resolvedItems = $this->payloadResolver->resolveItems($rawItems, $defaultWarehouseId);

            $headerData['items'] = $resolvedItems;
            $calculatedData = $this->calculator->calculate($headerData);

            $itemsToCreate = $calculatedData['items'] ?? [];
            unset($calculatedData['items']);

            $quotationNo = $calculatedData['quotation_no']
                ?? nextDocumentNumber(DocumentType::QUOTATION);

            /** @var SalesQuotation $quotation */
            $quotation = SalesQuotation::query()->create([
                ...$calculatedData,
                'quotation_no' => $quotationNo,
                'status' => $calculatedData['status'] ?? SalesQuotationStatusEnum::DRAFT->value,
                'created_by' => auth()->id(),
            ]);

            foreach ($itemsToCreate as $itemData) {
                SalesQuotationItem::query()->create([
                    ...$itemData,
                    'sales_quotation_id' => $quotation->id,
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
