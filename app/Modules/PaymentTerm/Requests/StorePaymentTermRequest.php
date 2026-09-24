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
             * Number of days during which an early-payment
             * discount is available.
             * @example 10
             */
            'discount_days' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            /**
             * Early-payment discount percentage.
             * @example 2
             */
            'discount_percent' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            /**
             * Additional grace period after the normal due date.
             * @example 5
             */
            'grace_days' => [
                'sometimes',
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

    public function after(): array
    {
        return [
            function ($validator) {

                $dueDays = (int) $this->input(
                    'due_days',
                    0
                );

                $discountDays = (int) $this->input(
                    'discount_days',
                    0
                );

                if ($discountDays > $dueDays) {
                    $validator->errors()->add(
                        'discount_days',
                        'Discount days cannot exceed due days.'
                    );
                }
            },
        ];
    }
}