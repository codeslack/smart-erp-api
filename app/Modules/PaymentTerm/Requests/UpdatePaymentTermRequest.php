<?php

namespace App\Modules\PaymentTerm\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdatePaymentTermRequest extends BaseRequest
{
    public function rules(): array
    {
        $paymentTerm = $this->route('paymentTerm');

        return [

            /**
             * Unique payment term code within the tenant,
             * excluding the current payment term.
             * @example NET30
             */
            'code' => [
                'sometimes',
                'string',
                'max:50',

                TenantRule::uniqueIgnore(
                    'payment_terms',
                    'code',
                    $paymentTerm->id
                ),
            ],

            /**
             * Payment term name, unique within the tenant,
             * excluding the current payment term.
             * @example Net 30 Days
             */
            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'payment_terms',
                    'name',
                    $paymentTerm->id
                ),
            ],

            /**
             * Number of days allowed for payment.
             * @example 30
             */
            'due_days' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            /**
             * A brief description of the payment term.
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