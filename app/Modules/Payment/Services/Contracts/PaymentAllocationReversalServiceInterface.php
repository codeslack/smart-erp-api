<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Models\Payment;

interface PaymentAllocationReversalServiceInterface
{
    public function reverse(
        Payment $payment
    ): void;
}