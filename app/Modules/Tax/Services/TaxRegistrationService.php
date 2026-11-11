<?php

namespace App\Modules\Tax\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tax\Domain\TaxRegistrationRules;
use App\Modules\Tax\Models\TaxRegistration;

use App\Modules\Tax\Repositories\Contracts\TaxRegistrationRepositoryInterface;

class TaxRegistrationService
{
    public function __construct(
        protected TaxRegistrationRepositoryInterface $registrations,
        protected TaxRegistrationRules $rules,
    ) {
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->registrations->paginate($perPage);
    }

    public function findByUuid(
        string $uuid
    ): ?TaxRegistration {
        return $this->registrations->findByUuid($uuid);
    }

    public function create(
        array $data
    ): TaxRegistration {
        return DB::transaction(function () use ($data) {
            $registrationType =
                $data['registration_type'];

            $registrationNumber = trim(
                $data['registration_number']
            );

            $registrableType =
                $data['registrable_type'];

            $registrableId = (int) (
                $data['registrable_id']
            );

            $this->rules->validateRegistrable(
                $registrableType,
                $registrableId
            );

            $effectiveFrom =
                $data['effective_from'];

            $effectiveTo =
                $data['effective_to'] ?? null;

            $isActive =
                $data['is_active'] ?? true;

            $isPrimary =
                $data['is_primary'] ?? false;

            $this->rules->validateRegistrationType(
                $registrationType
            );

            $this->rules->validateRegistrationNumber(
                $registrationType,
                $registrationNumber
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validatePrimaryRegistration(
                $isPrimary,
                $isActive
            );

            $this->validateRegistrationNumber(
                $registrationType,
                $registrationNumber
            );

            if ($isPrimary) {
                $this->validatePrimaryRegistration(
                    $registrableType,
                    $registrableId
                );
            }

            return $this->registrations->create([
                'registrable_type' =>
                    $registrableType,

                'registrable_id' =>
                    $registrableId,

                'registration_type' =>
                    $registrationType,

                'registration_number' =>
                    $registrationNumber,

                'country_code' =>
                    $data['country_code'] ?? null,

                'state_code' =>
                    $data['state_code'] ?? null,

                'is_primary' =>
                    $isPrimary,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);
        });
    }

    public function update(
        TaxRegistration $registration,
        array $data
    ): TaxRegistration {
        return DB::transaction(function () use (
            $registration,
            $data
        ) {
            $registrableType =
                $data['registrable_type']
                ?? $registration->registrable_type;

            $registrableId = (int) (
                $data['registrable_id']
                ?? $registration->registrable_id
            );

            $this->rules->validateRegistrable(
                $registrableType,
                $registrableId
            );

            $registrationType =
                $data['registration_type']
                ?? $registration->registration_type;

            $registrationNumber = isset(
                $data['registration_number']
            )
                ? trim($data['registration_number'])
                : $registration->registration_number;

            $effectiveFrom =
                $data['effective_from']
                ?? $registration->effective_from->toDateString();

            $effectiveTo = array_key_exists(
                'effective_to',
                $data
            )
                ? $data['effective_to']
                : $registration->effective_to?->toDateString();

            $isActive =
                $data['is_active']
                ?? $registration->is_active;

            $isPrimary =
                $data['is_primary']
                ?? $registration->is_primary;

            $countryCode = array_key_exists(
                'country_code',
                $data
            )
                ? $data['country_code']
                : $registration->country_code;

            $stateCode = array_key_exists(
                'state_code',
                $data
            )
                ? $data['state_code']
                : $registration->state_code;

            $this->rules->validateRegistrationType(
                $registrationType
            );

            $this->rules->validateRegistrationNumber(
                $registrationType,
                $registrationNumber
            );

            $this->rules->validateEffectivePeriod(
                $effectiveFrom,
                $effectiveTo
            );

            $this->rules->validatePrimaryRegistration(
                $isPrimary,
                $isActive
            );

            $this->validateRegistrationNumber(
                $registrationType,
                $registrationNumber,
                $registration->getKey()
            );

            if ($isPrimary) {
                $this->validatePrimaryRegistration(
                    $registrableType,
                    $registrableId,
                    $registration->getKey()
                );
            }

            $this->registrations->update($registration, [
                'registrable_type' =>
                    $registrableType,

                'registrable_id' =>
                    $registrableId,

                'registration_type' =>
                    $registrationType,

                'registration_number' =>
                    $registrationNumber,

                'country_code' =>
                    $countryCode,

                'state_code' =>
                    $stateCode,

                'is_primary' =>
                    $isPrimary,

                'is_active' =>
                    $isActive,

                'effective_from' =>
                    $effectiveFrom,

                'effective_to' =>
                    $effectiveTo,
            ]);

            return $registration->refresh();
        });
    }

    public function delete(
        TaxRegistration $registration
    ): bool {
        return DB::transaction(
            fn () => $this->registrations->delete(
                $registration
            )
        );
    }

    protected function validateRegistrationNumber(
        string $registrationType,
        string $registrationNumber,
        ?int $ignoreId = null
    ): void {
        if (
            $this->registrations->existsByRegistrationNumber(
                $registrationType,
                $registrationNumber,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                'Tax registration number already exists.'
            );
        }
    }

    protected function validatePrimaryRegistration(
        string $registrableType,
        int $registrableId,
        ?int $ignoreId = null
    ): void {
        if (
            $this->registrations->existsPrimaryForRegistrable(
                $registrableType,
                $registrableId,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                'A primary tax registration already exists for this entity.'
            );
        }
    }
}