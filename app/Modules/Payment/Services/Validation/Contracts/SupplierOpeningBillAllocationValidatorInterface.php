<?php

namespace App\Modules\Payment\Services\Validation\Contracts;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

interface SupplierOpeningBillAllocationValidatorInterface
{
    public function validate(
        Payment $payment,
        PaymentAllocation $allocation
    ): void;
}