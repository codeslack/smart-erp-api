<?php

namespace App\Modules\Customer\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Core\Enums\CreditControlEnum;
use App\Core\Enums\OpeningBalanceTypeEnum;

class StoreCustomerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::unique(
                    'customers',
                    'name'
                ),
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
                TenantRule::unique(
                    'customers',
                    'code'
                ),
            ],

            'contact_person' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'email' => [
                'nullable',
                'email',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'payment_term_id' => [
                'nullable',
                TenantRule::exists(
                    'payment_terms',
                    'id'
                ),
            ],

            'credit_days' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'credit_limit' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'credit_control' => [
                'sometimes',
                'string',
                Rule::in(
                    array_column(
                        CreditControlEnum::cases(),
                        'value'
                    )
                ),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Opening Bills
            |--------------------------------------------------------------------------
            */

            'opening_bills' => [
                'sometimes',
                'array',
            ],

            'opening_bills.*.bill_no' => [
                'required',
                'string',
                'max:100',
            ],

            'opening_bills.*.bill_date' => [
                'required',
                'date',
            ],

            'opening_bills.*.due_date' => [
                'nullable',
                'date',
            ],

            'opening_bills.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'opening_bills.*.balance_amount' => [
                'required',
                'numeric',
                'gte:0',
            ],

            'opening_bills.*.balance_type' => [
                'required',
                'string',
                Rule::in(
                    array_column(
                        OpeningBalanceTypeEnum::cases(),
                        'value'
                    )
                ),
            ],

            'opening_bills.*.notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                foreach (
                    $this->input('opening_bills', []) as $index => $bill
                ) {
                    $amount =
                        isset($bill['amount'])
                            ? (float) $bill['amount']
                            : null;

                    $balanceAmount =
                        isset($bill['balance_amount'])
                            ? (float) $bill['balance_amount']
                            : null;

                    if (
                        $amount !== null
                        && $balanceAmount !== null
                        && $balanceAmount > $amount
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.balance_amount",
                            'The balance amount cannot exceed the bill amount.'
                        );
                    }

                    if (
                        !empty($bill['bill_date'])
                        && !empty($bill['due_date'])
                        && strtotime($bill['due_date'])
                            < strtotime($bill['bill_date'])
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.due_date",
                            'The due date must be on or after the bill date.'
                        );
                    }
                }
            },
        ];
    }
}