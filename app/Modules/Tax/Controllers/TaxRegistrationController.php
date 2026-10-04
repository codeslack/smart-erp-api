<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxRegistrationCreateRequest;
use App\Modules\Tax\Requests\TaxRegistrationUpdateRequest;

use App\Modules\Tax\Resources\TaxRegistrationResource;

use App\Modules\Tax\Services\TaxRegistrationService;

class TaxRegistrationController extends ApiController
{
    public function __construct(
        protected TaxRegistrationService $taxRegistrations
    ) {}

    public function index(): JsonResponse
    {
        $taxRegistrations =
            $this->taxRegistrations->paginate();

        return $this->success(
            TaxRegistrationResource::collection(
                $taxRegistrations
            ),
            'Tax registrations retrieved successfully.'
        );
    }

    public function store(
        TaxRegistrationCreateRequest $request
    ): JsonResponse {
        $taxRegistration =
            $this->taxRegistrations->create(
                $request->validated()
            );

        return $this->success(
            new TaxRegistrationResource(
                $taxRegistration
            ),
            'Tax registration created successfully.',
            201
        );
    }

    public function show(
        string $taxRegistration
    ): JsonResponse {
        $model =
            $this->taxRegistrations->findByUuid(
                $taxRegistration
            );

        if ($model === null) {
            return $this->error(
                'Tax registration not found.',
                null,
                404
            );
        }

        return $this->success(
            new TaxRegistrationResource($model),
            'Tax registration retrieved successfully.'
        );
    }

    public function update(
        TaxRegistrationUpdateRequest $request,
        string $taxRegistration
    ): JsonResponse {
        $model =
            $this->taxRegistrations->findByUuid(
                $taxRegistration
            );

        if ($model === null) {
            return $this->error(
                'Tax registration not found.',
                null,
                404
            );
        }

        $model =
            $this->taxRegistrations->update(
                $model,
                $request->validated()
            );

        return $this->success(
            new TaxRegistrationResource($model),
            'Tax registration updated successfully.'
        );
    }

    public function destroy(
        string $taxRegistration
    ): JsonResponse {
        $model =
            $this->taxRegistrations->findByUuid(
                $taxRegistration
            );

        if ($model === null) {
            return $this->error(
                'Tax registration not found.',
                null,
                404
            );
        }

        $this->taxRegistrations->delete($model);

        return $this->success(
            null,
            'Tax registration deleted successfully.'
        );
    }
}