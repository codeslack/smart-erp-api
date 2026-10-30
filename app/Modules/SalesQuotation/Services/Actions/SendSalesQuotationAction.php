<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;

class SendSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationValidator $validator
    ) {}

    public function execute(SalesQuotation $quotation): SalesQuotation
    {
        $this->validator->validateForSend($quotation);

        $quotation->update([
            'status' => SalesQuotationStatusEnum::SENT->value,
            'sent_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $quotation->fresh([
            'items.product',
            'customer',
            'warehouse',
        ]);
    }
}
