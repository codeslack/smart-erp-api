<?php

namespace App\Modules\Tax\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface TaxRegistrationRepositoryInterface extends BaseRepositoryInterface
{
    public function existsByRegistrationNumber(
        string $registrationType,
        string $registrationNumber,
        ?int $ignoreId = null
    ): bool;

    public function existsPrimaryForRegistrable(
        string $registrableType,
        int $registrableId,
        ?int $ignoreId = null
    ): bool;
}
