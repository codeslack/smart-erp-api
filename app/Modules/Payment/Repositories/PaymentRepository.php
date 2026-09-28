<?php

namespace App\Modules\Payment\Repositories;

use Illuminate\Database\Eloquent\Builder;

use App\Modules\Payment\Models\Payment;

use App\Core\Repositories\BaseRepository;
use App\Modules\Payment\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository 
    extends BaseRepository 
    implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function findByPaymentNo(
        string $paymentNo
    ): ?Payment {
        return $this->model
            ->newQuery()
            ->where('payment_no', $paymentNo)
            ->first();
    }

    public function queryByParty(
        string $partyType,
        int $partyId
    ): Builder {
        return $this->model
            ->newQuery()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId);
    }
}