<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;

class RejectSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationValidator $validator
    ) {}

    public function execute(SalesQuotation $quotation, ?string $reason = null): SalesQuotation
    {
        $this->validator->validateForRejection($quotation);

        $quotation->update([
            'status' => SalesQuotationStatusEnum::REJECTED->value,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'updated_by' => auth()->id(),
        ]);

        return $quotation->fresh([
            'items.product',
            'customer',
            'warehouse',
        ]);
    }
}
