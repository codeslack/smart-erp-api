<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxGroupComponentCreateRequest;
use App\Modules\Tax\Requests\TaxGroupComponentUpdateRequest;

use App\Modules\Tax\Resources\TaxGroupComponentResource;

use App\Modules\Tax\Services\TaxGroupComponentService;

class TaxGroupComponentController extends ApiController
{
    public function __construct(
        protected TaxGroupComponentService $taxGroupComponents
    ) {}

    public function index(): JsonResponse
    {
        $taxGroupComponents = $this->taxGroupComponents->paginate();

        return $this->success(
            TaxGroupComponentResource::collection(
                $taxGroupComponents
            ),
            'Tax group components retrieved successfully.'
        );
    }

    public function store(
        TaxGroupComponentCreateRequest $request
    ): JsonResponse {
        $taxGroupComponent = $this->taxGroupComponents->create(
            $request->validated()
        );

        return $this->success(
            new TaxGroupComponentResource($taxGroupComponent),
            'Tax group component created successfully.',
            201
        );
    }

    public function show(
        string $taxGroupComponent
    ): JsonResponse {
        $model = $this->taxGroupComponents->findByUuid(
            $taxGroupComponent
        );

        if ($model === null) {
            return $this->error(
                'Tax group component not found.',
                null,
                404
            );
        }

        return $this->success(
            new TaxGroupComponentResource($model),
            'Tax group component retrieved successfully.'
        );
    }

    public function update(
        TaxGroupComponentUpdateRequest $request,
        string $taxGroupComponent
    ): JsonResponse {
        $model = $this->taxGroupComponents->findByUuid(
            $taxGroupComponent
        );

        if ($model === null) {
            return $this->error(
                'Tax group component not found.',
                null,
                404
            );
        }

        $model = $this->taxGroupComponents->update(
            $model,
            $request->validated()
        );

        return $this->success(
            new TaxGroupComponentResource($model),
            'Tax group component updated successfully.'
        );
    }

    public function destroy(
        string $taxGroupComponent
    ): JsonResponse {
        $model = $this->taxGroupComponents->findByUuid(
            $taxGroupComponent
        );

        if ($model === null) {
            return $this->error(
                'Tax group component not found.',
                null,
                404
            );
        }

        $this->taxGroupComponents->delete($model);

        return $this->success(
            null,
            'Tax group component deleted successfully.'
        );
    }
}