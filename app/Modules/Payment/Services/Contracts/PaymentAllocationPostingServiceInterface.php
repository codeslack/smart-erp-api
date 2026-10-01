<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Models\Payment;

interface PaymentAllocationPostingServiceInterface
{
    public function post(
        Payment $payment
    ): void;
}