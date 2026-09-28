<?php

namespace App\Modules\Payment\Repositories\Contracts;

use App\Modules\Payment\Models\Payment;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PaymentRepositoryInterface
    extends BaseRepositoryInterface
{

    public function findByPaymentNo(
        string $paymentNo
    ): ?Payment;

    public function queryByParty(
        string $partyType,
        int $partyId
    );
}
