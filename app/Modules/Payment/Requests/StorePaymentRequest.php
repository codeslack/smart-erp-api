<?php

namespace App\Modules\Payment\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;

class StorePaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        $partyTable = match (
            $this->input('party_type')
        ) {
            PaymentPartyTypeEnum::CUSTOMER->value
                => 'customers',

            PaymentPartyTypeEnum::SUPPLIER->value
                => 'suppliers',

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
                'required',
                'integer',
                'min:1',

                $partyTable
                    ? TenantRule::exists(
                        $partyTable,
                        'id'
                    )
                    : 'prohibited',
            ],

            'account_id' => [
                'required',
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
                $this->validateAllocations(
                    $validator
                );
            },
        ];
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