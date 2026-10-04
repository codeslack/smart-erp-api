<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Repositories\Contracts\TaxRegistrationRepositoryInterface;

class TaxRegistrationRepository extends BaseRepository implements TaxRegistrationRepositoryInterface
{
    public function __construct(
        TaxRegistration $model
    ) {
        parent::__construct($model);
    }

    public function existsByRegistrationNumber(
        string $registrationType,
        string $registrationNumber,
        ?int $ignoreId = null
    ): bool {
        $query = $this->model->newQuery()
            ->where(
                'registration_type',
                $registrationType
            )
            ->where(
                'registration_number',
                $registrationNumber
            );

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    public function existsPrimaryForRegistrable(
        string $registrableType,
        int $registrableId,
        ?int $ignoreId = null
    ): bool {
        $query = $this->model->newQuery()
            ->where(
                'registrable_type',
                $registrableType
            )
            ->where(
                'registrable_id',
                $registrableId
            )
            ->where('is_primary', true)
            ->where('is_active', true);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}