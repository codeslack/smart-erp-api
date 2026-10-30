<?php

namespace App\Modules\SalesQuotation\Services\Validators;

use App\Core\Exceptions\BusinessException;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;

class SalesQuotationValidator
{
    public function validateForUpdate(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canEdit()) {
            throw new BusinessException(
                "Cannot modify a quotation with status [{$quotation->status}]."
            );
        }
    }

    public function validateForSend(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canSend()) {
            throw new BusinessException(
                "Only draft quotations can be marked as sent. Current status: [{$quotation->status}]."
            );
        }
    }

    public function validateForAcceptance(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canAccept()) {
            throw new BusinessException(
                "Quotation with status [{$quotation->status}] cannot be accepted."
            );
        }

        if ($quotation->expiry_date && $quotation->expiry_date->isPast()) {
            throw new BusinessException(
                'Cannot accept an expired quotation.'
            );
        }
    }

    public function validateForRejection(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canReject()) {
            throw new BusinessException(
                "Quotation with status [{$quotation->status}] cannot be rejected."
            );
        }
    }

    public function validateForConversion(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canConvert()) {
            throw new BusinessException(
                "Quotation with status [{$quotation->status}] cannot be converted."
            );
        }

        if ($quotation->isConverted()) {
            throw new BusinessException(
                'Quotation has already been converted.'
            );
        }
    }

    public function validateForCancellation(SalesQuotation $quotation): void
    {
        $status = SalesQuotationStatusEnum::tryFrom($quotation->status);
        if ($status && ! $status->canCancel()) {
            throw new BusinessException(
                "Cannot cancel quotation with status [{$quotation->status}]."
            );
        }
    }

    public function validateForDeletion(SalesQuotation $quotation): void
    {
        if (! $quotation->isDraft()) {
            throw new BusinessException(
                "Only draft quotations can be deleted. Current status: [{$quotation->status}]."
            );
        }
    }
}
