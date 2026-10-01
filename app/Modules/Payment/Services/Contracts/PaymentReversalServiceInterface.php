<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Models\Payment;

interface PaymentReversalServiceInterface
{
    public function reverse(
        Payment $payment
    ): Payment;
}