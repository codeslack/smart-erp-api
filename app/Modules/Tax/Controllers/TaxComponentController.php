<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxComponentCreateRequest;
use App\Modules\Tax\Requests\TaxComponentUpdateRequest;

use App\Modules\Tax\Resources\TaxComponentResource;

use App\Modules\Tax\Services\TaxComponentService;

class TaxComponentController extends ApiController
{
    public function __construct(
        protected TaxComponentService $taxComponents
    ) {}

    public function index(): JsonResponse
    {
        $taxComponents = $this->taxComponents->paginate();

        return $this->success(
            TaxComponentResource::collection(
                $taxComponents
            ),
            'Tax components retrieved successfully.'
        );
    }

    public function store(
        TaxComponentCreateRequest $request
    ): JsonResponse {
        $taxComponent = $this->taxComponents->create(
            $request->validated()
        );

        return $this->success(
            new TaxComponentResource($taxComponent),
            'Tax component created successfully.',
            201
        );
    }

    public function show(
        string $taxComponent
    ): JsonResponse {
        $model = $this->taxComponents->findByUuid(
            $taxComponent
        );

        return $this->success(
            new TaxComponentResource($model),
            'Tax component retrieved successfully.'
        );
    }

    public function update(
        TaxComponentUpdateRequest $request,
        string $taxComponent
    ): JsonResponse {
        $model = $this->taxComponents->findByUuid(
            $taxComponent
        );

        $model = $this->taxComponents->update(
            $model,
            $request->validated()
        );

        return $this->success(
            new TaxComponentResource($model),
            'Tax component updated successfully.'
        );
    }

    public function destroy(
        string $taxComponent
    ): JsonResponse {
        $model = $this->taxComponents->findByUuid(
            $taxComponent
        );

        $this->taxComponents->delete($model);

        return $this->success(
            null,
            'Tax component deleted successfully.'
        );
    }
}