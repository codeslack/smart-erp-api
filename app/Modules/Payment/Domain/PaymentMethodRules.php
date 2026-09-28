<?php

namespace App\Modules\Payment\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Payment\Enums\PaymentMethodEnum;

class PaymentMethodRules
{
    public function validate(
        PaymentMethodEnum $method,
        ?string $transactionNo = null,
        ?string $chequeNo = null,
        ?string $chequeDate = null,
        ?string $transferNo = null,
        ?string $transferDate = null,
    ): void {
        match ($method) {
            PaymentMethodEnum::CASH => null,

            PaymentMethodEnum::UPI =>
                $this->validateUpi($transactionNo),

            PaymentMethodEnum::CARD =>
                $this->validateCard($transactionNo),

            PaymentMethodEnum::CHEQUE =>
                $this->validateCheque(
                    $chequeNo,
                    $chequeDate,
                ),

            PaymentMethodEnum::BANK_TRANSFER =>
                $this->validateBankTransfer(
                    $transferNo,
                    $transferDate,
                ),

            PaymentMethodEnum::OTHER => null,
        };
    }

    protected function validateUpi(
        ?string $transactionNo,
    ): void {
        if (blank($transactionNo)) {
            throw new BusinessException(
                'UPI transaction number is required.',
                'UPI_TRANSACTION_NO_REQUIRED'
            );
        }
    }

    protected function validateCard(
        ?string $transactionNo,
    ): void {
        if (blank($transactionNo)) {
            throw new BusinessException(
                'Card transaction number is required.',
                'CARD_TRANSACTION_NO_REQUIRED'
            );
        }
    }

    protected function validateCheque(
        ?string $chequeNo,
        ?string $chequeDate,
    ): void {
        if (blank($chequeNo)) {
            throw new BusinessException(
                'Cheque number is required.',
                'CHEQUE_NO_REQUIRED'
            );
        }

        if (blank($chequeDate)) {
            throw new BusinessException(
                'Cheque date is required.',
                'CHEQUE_DATE_REQUIRED'
            );
        }
    }

    protected function validateBankTransfer(
        ?string $transferNo,
        ?string $transferDate,
    ): void {
        if (blank($transferNo)) {
            throw new BusinessException(
                'Transfer number is required.',
                'TRANSFER_NO_REQUIRED'
            );
        }

        if (blank($transferDate)) {
            throw new BusinessException(
                'Transfer date is required.',
                'TRANSFER_DATE_REQUIRED'
            );
        }
    }
}