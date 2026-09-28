<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Models\Payment;

interface PaymentPostingServiceInterface
{
    public function confirm(
        Payment $payment
    ): Payment;
}