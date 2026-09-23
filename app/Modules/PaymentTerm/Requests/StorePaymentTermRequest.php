<?php

namespace App\Modules\PaymentTerm\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StorePaymentTermRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /**
             * Unique payment term code within the tenant.
             * @example NET30
             */
            'code' => [
                'required',
                'string',
                'max:50',

                TenantRule::unique(
                    'payment_terms',
                    'code'
                ),
            ],

            /**
             * Payment term name, unique within the tenant.
             * @example Net 30 Days
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'payment_terms',
                    'name'
                ),
            ],

            /**
             * Number of days allowed for payment.
             * @example 30
             */
            'due_days' => [
                'required',
                'integer',
                'min:0',
            ],

            /**
             * A brief description of the payment term.
             * @example Payment due within 30 days.
             */
            'description' => [
                'nullable',
                'string',
            ],

            /**
             * Boolean flag indicating whether the payment term
             * is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}