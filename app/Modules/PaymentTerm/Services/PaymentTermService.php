<?php

namespace App\Modules\PaymentTerm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Services\BaseService;

use App\Modules\PaymentTerm\Models\PaymentTerm;

use App\Modules\PaymentTerm\Repositories\Contracts\PaymentTermRepositoryInterface;

class PaymentTermService extends BaseService
{
    public function __construct(
        protected PaymentTermRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate(
                $perPage
            );
    }

    public function create(
        array $data
    ): PaymentTerm {

        return DB::transaction(
            fn () => $this->repository
                ->create(
                    $data
                )
        );
    }

    public function update(
        PaymentTerm $paymentTerm,
        array $data
    ): PaymentTerm {

        return DB::transaction(
            fn () => $this->repository
                ->update(
                    $paymentTerm,
                    $data
                )
        );
    }

    public function delete(
        PaymentTerm $paymentTerm
    ): bool {

        return DB::transaction(
            fn () => $this->repository
                ->delete(
                    $paymentTerm
                )
        );
    }

    public function findById(
        int $id
    ): ?PaymentTerm {

        return $this->repository
            ->findById(
                $id
            );
    }

    public function findByUuid(
        string $uuid
    ): ?PaymentTerm {

        return $this->repository
            ->findByUuid(
                $uuid
            );
    }

    public function findByCode(
        string $code
    ): ?PaymentTerm {

        return $this->repository
            ->findByCode(
                $code
            );
    }
}