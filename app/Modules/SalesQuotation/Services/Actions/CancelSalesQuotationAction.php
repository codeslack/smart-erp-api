<?php

namespace App\Modules\SalesQuotation\Services\Actions;

use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;

class CancelSalesQuotationAction
{
    public function __construct(
        protected SalesQuotationValidator $validator
    ) {}

    public function execute(SalesQuotation $quotation, ?string $reason = null): SalesQuotation
    {
        $this->validator->validateForCancellation($quotation);

        $notes = $quotation->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\nCancellation reason: {$reason}" : "Cancellation reason: {$reason}";
        }

        $quotation->update([
            'status' => SalesQuotationStatusEnum::CANCELLED->value,
            'notes' => $notes,
            'updated_by' => auth()->id(),
        ]);

        return $quotation->fresh([
            'items.product',
            'customer',
            'warehouse',
        ]);
    }
}
