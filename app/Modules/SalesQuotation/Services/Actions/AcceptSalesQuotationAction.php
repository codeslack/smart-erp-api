<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;

class AcceptSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationValidator $validator
    ) {}

    public function execute(SalesQuotation $quotation): SalesQuotation
    {
        $this->validator->validateForAcceptance($quotation);

        $quotation->update([
            'status' => SalesQuotationStatusEnum::ACCEPTED->value,
            'accepted_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $quotation->fresh([
            'items.product',
            'customer',
            'warehouse',
        ]);
    }
}
