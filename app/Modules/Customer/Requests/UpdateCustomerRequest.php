<?php

namespace App\Modules\Customer\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Core\Enums\CreditControlEnum;
use App\Core\Enums\OpeningBalanceTypeEnum;

class UpdateCustomerRequest extends BaseRequest
{
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                TenantRule::uniqueIgnore(
                    'customers',
                    'name',
                    $customer?->id
                ),
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                TenantRule::uniqueIgnore(
                    'customers',
                    'code',
                    $customer?->id
                ),
            ],

            'contact_person' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
            ],

            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'tax_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'payment_term_id' => [
                'sometimes',
                'nullable',
                TenantRule::exists(
                    'payment_terms',
                    'id'
                ),
            ],

            'credit_days' => [
                'sometimes',
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

            'opening_bills.*.uuid' => [
                'sometimes',
                'nullable',
                'string',
                'uuid',
            ],

            'opening_bills.*.delete' => [
                'sometimes',
                'boolean',
            ],

            'opening_bills.*.bill_no' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'opening_bills.*.bill_date' => [
                'sometimes',
                'date',
            ],

            'opening_bills.*.due_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'opening_bills.*.amount' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'opening_bills.*.balance_amount' => [
                'sometimes',
                'numeric',
                'gte:0',
            ],

            'opening_bills.*.balance_type' => [
                'sometimes',
                'string',
                Rule::in(
                    array_column(
                        OpeningBalanceTypeEnum::cases(),
                        'value'
                    )
                ),
            ],

            'opening_bills.*.notes' => [
                'sometimes',
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
                    $delete =
                        (bool) (
                            $bill['delete'] ?? false
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Delete row
                    |--------------------------------------------------------------------------
                    */

                    if ($delete) {

                        if (
                            empty($bill['uuid'])
                        ) {
                            $validator->errors()->add(
                                "opening_bills.{$index}.uuid",
                                'A bill UUID is required when deleting an opening bill.'
                            );
                        }

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Create / Update row
                    |--------------------------------------------------------------------------
                    */

                    if (
                        empty($bill['bill_no'])
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.bill_no",
                            'The bill number is required.'
                        );
                    }

                    if (
                        empty($bill['bill_date'])
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.bill_date",
                            'The bill date is required.'
                        );
                    }

                    if (
                        !array_key_exists(
                            'amount',
                            $bill
                        )
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.amount",
                            'The bill amount is required.'
                        );
                    }

                    if (
                        !array_key_exists(
                            'balance_amount',
                            $bill
                        )
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.balance_amount",
                            'The balance amount is required.'
                        );
                    }

                    if (
                        empty($bill['balance_type'])
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.balance_type",
                            'The balance type is required.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Balance cannot exceed original amount
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset($bill['amount'])
                        && isset($bill['balance_amount'])
                        && (float) $bill['balance_amount']
                            > (float) $bill['amount']
                    ) {
                        $validator->errors()->add(
                            "opening_bills.{$index}.balance_amount",
                            'The balance amount cannot exceed the bill amount.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Due date cannot be before bill date
                    |--------------------------------------------------------------------------
                    */

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