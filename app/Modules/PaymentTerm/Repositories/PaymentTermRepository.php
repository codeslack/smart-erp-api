<?php

namespace App\Modules\PaymentTerm\Repositories;

use App\Modules\PaymentTerm\Models\PaymentTerm;

use App\Core\Repositories\BaseRepository;

use App\Modules\PaymentTerm\Repositories\Contracts\PaymentTermRepositoryInterface;

/**
 * @extends BaseRepository<PaymentTerm>
 */
class PaymentTermRepository extends BaseRepository
    implements PaymentTermRepositoryInterface
{
    public function __construct(
        PaymentTerm $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByUuid(
        string $uuid
    ): ?PaymentTerm {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function findByCode(
        string $code
    ): ?PaymentTerm {

        return $this->model
            ->newQuery()
            ->where(
                'code',
                $code
            )
            ->first();
    }
}