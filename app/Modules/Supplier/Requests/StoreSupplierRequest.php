<?php

namespace App\Modules\Supplier\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Core\Enums\CreditControlEnum;

class StoreSupplierRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::unique('suppliers', 'name'),
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
                TenantRule::unique('suppliers', 'code'),
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
                TenantRule::exists('payment_terms', 'id'),
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
                'in:' . implode(
                    ',',
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
        ];
    }
}
