<?php

namespace App\Modules\PaymentTerm\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdatePaymentTermRequest extends BaseRequest
{
    public function rules(): array
    {
        $paymentTerm = $this->route('payment_term');

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
                    $this->route('payment_term')->due_days
                );

                $discountDays = (int) $this->input(
                    'discount_days',
                    $this->route('payment_term')->discount_days
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