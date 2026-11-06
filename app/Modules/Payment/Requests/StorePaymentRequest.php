<?php

namespace App\Modules\Payment\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

class StorePaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        $partyTable = match (
            $this->input('party_type')
        ) {
            PaymentPartyTypeEnum::CUSTOMER->value => 'customers',
            PaymentPartyTypeEnum::SUPPLIER->value => 'suppliers',
            default => null,
        };

        return [
            'payment_no' => [
                'required',
                'string',
                'max:50',
                TenantRule::unique(
                    'payments',
                    'payment_no'
                ),
            ],

            'payment_type' => [
                'required',
                'string',
                Rule::in(
                    array_column(
                        PaymentTypeEnum::cases(),
                        'value'
                    )
                ),
            ],

            'payment_date' => [
                'required',
                'date',
            ],

            'party_type' => [
                'required',
                'string',
                Rule::in(
                    array_column(
                        PaymentPartyTypeEnum::cases(),
                        'value'
                    )
                ),
            ],

            'party_id' => [
                'required_if:party_type,customer,supplier',
                'prohibited_if:party_type,other',
                'nullable',
                'integer',
                'min:1',

                $partyTable
                    ? TenantRule::exists(
                        $partyTable,
                        'id'
                    )
                    : 'prohibited',
            ],

            'purpose' => [
                'required',
                'string',
                Rule::in(
                    array_column(
                        PaymentPurposeEnum::cases(),
                        'value'
                    )
                ),
            ],

            'account_id' => [
                'required',
                'integer',
                TenantRule::exists(
                    'chart_of_accounts',
                    'id'
                ),
            ],

            'offset_account_id' => [
                'nullable',
                'integer',
                TenantRule::exists(
                    'chart_of_accounts',
                    'id'
                ),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'required',
                'string',
                Rule::in(
                    array_column(
                        PaymentMethodEnum::cases(),
                        'value'
                    )
                ),
            ],

            'reference_no' => [
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Manual Allocations
            |--------------------------------------------------------------------------
            */

            'allocations' => [
                'sometimes',
                'array',
            ],

            'allocations.*.document_type' => [
                'required',
                'string',
                'max:50',
            ],

            'allocations.*.document_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'allocations.*.allocated_amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validatePurposeCompatibility($validator);
                $this->validateAllocations($validator);
            },
        ];
    }

    protected function validatePurposeCompatibility(
        Validator $validator
    ): void {
        $purposeValue = $this->input('purpose');

        if (! is_string($purposeValue)) {
            return;
        }

        $purpose = PaymentPurposeEnum::tryFrom($purposeValue);

        if (! $purpose) {
            return;
        }

        $paymentTypeValue = $this->input('payment_type');

        if (! is_string($paymentTypeValue)) {
            return;
        }

        $paymentType = PaymentTypeEnum::tryFrom($paymentTypeValue);

        if (! $paymentType) {
            return;
        }

        if (
            $purpose->isReceipt()
            && $paymentType !== PaymentTypeEnum::RECEIPT
        ) {
            $validator->errors()->add(
                'payment_type',
                'The selected purpose requires a receipt transaction.'
            );
        }

        if (
            $purpose->isPayment()
            && $paymentType !== PaymentTypeEnum::PAYMENT
        ) {
            $validator->errors()->add(
                'payment_type',
                'The selected purpose requires a payment transaction.'
            );
        }

        $partyTypeValue = $this->input('party_type');

        if (! is_string($partyTypeValue)) {
            return;
        }

        $partyType = PaymentPartyTypeEnum::tryFrom($partyTypeValue);

        if (! $partyType) {
            return;
        }

        $requiresCustomer = match ($purpose) {
            PaymentPurposeEnum::CUSTOMER_PAYMENT,
            PaymentPurposeEnum::CUSTOMER_ADVANCE,
            PaymentPurposeEnum::CUSTOMER_OPENING_BALANCE_ADJUSTMENT,
            PaymentPurposeEnum::CUSTOMER_REFUND => true,

            default => false,
        };

        $requiresSupplier = match ($purpose) {
            PaymentPurposeEnum::SUPPLIER_PAYMENT,
            PaymentPurposeEnum::SUPPLIER_ADVANCE,
            PaymentPurposeEnum::SUPPLIER_REFUND => true,

            default => false,
        };

        $requiresOther = match ($purpose) {
            PaymentPurposeEnum::OTHER_RECEIPT,
            PaymentPurposeEnum::OTHER_PAYMENT => true,

            default => false,
        };

        if (
            $requiresCustomer
            && ! $partyType->isCustomer()
        ) {
            $validator->errors()->add(
                'party_type',
                'The selected purpose requires a customer party.'
            );
        }

        if (
            $requiresSupplier
            && ! $partyType->isSupplier()
        ) {
            $validator->errors()->add(
                'party_type',
                'The selected purpose requires a supplier party.'
            );
        }

        if (
            $requiresOther
            && ! $partyType->isOther()
        ) {
            $validator->errors()->add(
                'party_type',
                'The selected purpose requires an other party type.'
            );
        }

        if (
            $purpose->isOtherReceipt()
            || $purpose->isOtherPayment()
        ) {
            if (! $this->filled('offset_account_id')) {
                $validator->errors()->add(
                    'offset_account_id',
                    'Offset account is required for this payment purpose.'
                );
            }
        }
    }

    protected function validateAllocations(
        Validator $validator
    ): void {
        $amount = $this->input('amount');

        if ($amount === null) {
            return;
        }

        $allocatedAmount = collect(
            $this->input('allocations', [])
        )->sum(
            fn ($allocation) =>
                isset($allocation['allocated_amount'])
                    ? (float) $allocation['allocated_amount']
                    : 0
        );

        if ($allocatedAmount > (float) $amount) {
            $validator->errors()->add(
                'allocations',
                'The total allocated amount cannot exceed the payment amount.'
            );
        }
    }
}