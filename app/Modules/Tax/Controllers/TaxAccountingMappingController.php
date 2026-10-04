<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxAccountingMappingCreateRequest;
use App\Modules\Tax\Requests\TaxAccountingMappingUpdateRequest;

use App\Modules\Tax\Resources\TaxAccountingMappingResource;

use App\Modules\Tax\Services\TaxAccountingMappingService;

class TaxAccountingMappingController extends ApiController
{
    public function __construct(
        protected TaxAccountingMappingService $taxAccountingMappings
    ) {}

    public function index(): JsonResponse
    {
        $taxAccountingMappings =
            $this->taxAccountingMappings->paginate();

        return $this->success(
            TaxAccountingMappingResource::collection(
                $taxAccountingMappings
            ),
            'Tax accounting mappings retrieved successfully.'
        );
    }

    public function store(
        TaxAccountingMappingCreateRequest $request
    ): JsonResponse {
        $taxAccountingMapping =
            $this->taxAccountingMappings->create(
                $request->validated()
            );

        return $this->success(
            new TaxAccountingMappingResource(
                $taxAccountingMapping
            ),
            'Tax accounting mapping created successfully.',
            201
        );
    }

    public function show(
        string $taxAccountingMapping
    ): JsonResponse {
        $model =
            $this->taxAccountingMappings->findByUuid(
                $taxAccountingMapping
            );

        if ($model === null) {
            return $this->error(
                'Tax accounting mapping not found.',
                null,
                404
            );
        }

        return $this->success(
            new TaxAccountingMappingResource($model),
            'Tax accounting mapping retrieved successfully.'
        );
    }

    public function update(
        TaxAccountingMappingUpdateRequest $request,
        string $taxAccountingMapping
    ): JsonResponse {
        $model =
            $this->taxAccountingMappings->findByUuid(
                $taxAccountingMapping
            );

        if ($model === null) {
            return $this->error(
                'Tax accounting mapping not found.',
                null,
                404
            );
        }

        $model =
            $this->taxAccountingMappings->update(
                $model,
                $request->validated()
            );

        return $this->success(
            new TaxAccountingMappingResource($model),
            'Tax accounting mapping updated successfully.'
        );
    }

    public function destroy(
        string $taxAccountingMapping
    ): JsonResponse {
        $model =
            $this->taxAccountingMappings->findByUuid(
                $taxAccountingMapping
            );

        if ($model === null) {
            return $this->error(
                'Tax accounting mapping not found.',
                null,
                404
            );
        }

        $this->taxAccountingMappings->delete($model);

        return $this->success(
            null,
            'Tax accounting mapping deleted successfully.'
        );
    }
}