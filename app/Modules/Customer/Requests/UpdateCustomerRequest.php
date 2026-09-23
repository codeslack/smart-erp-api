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
                'required_if:opening_bills.*.delete,true',
                'nullable',
                'string',
                'uuid',
            ],

            'opening_bills.*.delete' => [
                'sometimes',
                'boolean',
            ],

            'opening_bills.*.bill_no' => [
                'required_unless:opening_bills.*.delete,true',
                'string',
                'max:100',
            ],

            'opening_bills.*.bill_date' => [
                'required_unless:opening_bills.*.delete,true',
                'date',
            ],

            'opening_bills.*.due_date' => [
                'nullable',
                'date',
            ],

            'opening_bills.*.amount' => [
                'required_unless:opening_bills.*.delete,true',
                'numeric',
                'gt:0',
            ],

            'opening_bills.*.balance_amount' => [
                'required_unless:opening_bills.*.delete,true',
                'numeric',
                'gte:0',
            ],

            'opening_bills.*.balance_type' => [
                'required_unless:opening_bills.*.delete,true',
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
}