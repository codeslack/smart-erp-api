<?php

namespace App\Modules\Payment\Validation;

use Illuminate\Validation\Validator;

use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

class PaymentRequestValidator
{
    public function validate(Validator $validator): void
    {
        $data = $validator->getData();

        $this->validatePurposeCompatibility(
            $validator,
            $data
        );

        $this->validateAllocations(
            $validator,
            $data
        );
    }

    protected function validatePurposeCompatibility(
        Validator $validator,
        array $data
    ): void {
        $purposeValue = $data['purpose'] ?? null;
        $paymentTypeValue = $data['payment_type'] ?? null;
        $partyTypeValue = $data['party_type'] ?? null;

        $purpose = PaymentPurposeEnum::tryFrom(
            is_string($purposeValue) ? $purposeValue : ''
        );

        $paymentType = PaymentTypeEnum::tryFrom(
            is_string($paymentTypeValue) ? $paymentTypeValue : ''
        );

        $partyType = PaymentPartyTypeEnum::tryFrom(
            is_string($partyTypeValue) ? $partyTypeValue : ''
        );

        if (! $purpose || ! $paymentType || ! $partyType) {
            return;
        }

        if (
            $purpose->isReceipt()
            && ! $paymentType->isReceipt()
        ) {
            $validator->errors()->add(
                'payment_type',
                'The selected purpose requires a receipt transaction.'
            );
        }

        if (
            $purpose->isPayment()
            && ! $paymentType->isPayment()
        ) {
            $validator->errors()->add(
                'payment_type',
                'The selected purpose requires a payment transaction.'
            );
        }

        $requiredParty = match ($purpose) {
            PaymentPurposeEnum::CUSTOMER_PAYMENT,
            PaymentPurposeEnum::CUSTOMER_ADVANCE,
            PaymentPurposeEnum::CUSTOMER_OPENING_BALANCE_ADJUSTMENT,
            PaymentPurposeEnum::CUSTOMER_REFUND =>
                PaymentPartyTypeEnum::CUSTOMER,

            PaymentPurposeEnum::SUPPLIER_PAYMENT,
            PaymentPurposeEnum::SUPPLIER_ADVANCE,
            PaymentPurposeEnum::SUPPLIER_REFUND =>
                PaymentPartyTypeEnum::SUPPLIER,

            PaymentPurposeEnum::OTHER_RECEIPT,
            PaymentPurposeEnum::OTHER_PAYMENT =>
                PaymentPartyTypeEnum::OTHER,
        };

        if ($partyType !== $requiredParty) {
            $validator->errors()->add(
                'party_type',
                "The selected purpose requires a {$requiredParty->value} party."
            );
        }

        if (
            (
                $purpose->isOtherReceipt()
                || $purpose->isOtherPayment()
            )
            && empty($data['offset_account_id'])
        ) {
            $validator->errors()->add(
                'offset_account_id',
                'Offset account is required for this payment purpose.'
            );
        }
    }

    protected function validateAllocations(
        Validator $validator,
        array $data
    ): void {
        $purposeValue = $data['purpose'] ?? null;

        $purpose = PaymentPurposeEnum::tryFrom(
            is_string($purposeValue) ? $purposeValue : ''
        );

        $allocations = $data['allocations'] ?? [];

        if (! $purpose || ! is_array($allocations)) {
            return;
        }

        if (
            ! $purpose->allowsAllocations()
            && ! empty($allocations)
        ) {
            $validator->errors()->add(
                'allocations',
                'Allocations are not allowed for this payment purpose.'
            );

            return;
        }

        if (
            empty($allocations)
            || ! isset($data['amount'])
            || $validator->errors()->has('amount')
        ) {
            return;
        }

        $totalAllocated = '0.0000';

        foreach ($allocations as $allocation) {
            if (
                ! is_array($allocation)
                || ! isset($allocation['allocated_amount'])
                || ! is_numeric($allocation['allocated_amount'])
            ) {
                return;
            }

            $value = (string) $allocation['allocated_amount'];

            if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
                return;
            }

            $totalAllocated = bcadd(
                $totalAllocated,
                $value,
                4
            );
        }

        if (
            bccomp(
                $totalAllocated,
                (string) $data['amount'],
                4
            ) === 1
        ) {
            $validator->errors()->add(
                'allocations',
                'The total allocated amount cannot exceed the payment amount.'
            );
        }
    }
}
