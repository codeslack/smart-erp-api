<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface
{
    public function create(array $data): Payment;

    public function update(
        Payment $payment,
        array $data
    ): Payment;

    public function findByUuid(
        string $uuid
    ): Payment;

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator;

    public function delete(
        Payment $payment
    ): bool;
}