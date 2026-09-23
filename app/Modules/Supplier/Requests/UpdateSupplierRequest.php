<?php

namespace App\Modules\Supplier\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Core\Enums\CreditControlEnum;

class UpdateSupplierRequest extends BaseRequest
{
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                TenantRule::uniqueIgnore(
                    'suppliers',
                    'name',
                    $supplier?->id
                ),
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                TenantRule::uniqueIgnore(
                    'suppliers',
                    'code',
                    $supplier?->id
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
                TenantRule::exists('payment_terms', 'id'),
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
