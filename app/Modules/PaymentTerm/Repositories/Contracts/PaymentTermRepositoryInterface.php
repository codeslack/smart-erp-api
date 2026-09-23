<?php

namespace App\Modules\PaymentTerm\Repositories\Contracts;

use App\Modules\PaymentTerm\Models\PaymentTerm;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PaymentTermRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?PaymentTerm;

    public function findByCode(
        string $code
    ): ?PaymentTerm;
}