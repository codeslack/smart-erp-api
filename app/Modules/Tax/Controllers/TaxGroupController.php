<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxGroupCreateRequest;
use App\Modules\Tax\Requests\TaxGroupUpdateRequest;

use App\Modules\Tax\Resources\TaxGroupResource;

use App\Modules\Tax\Services\TaxGroupService;

class TaxGroupController extends ApiController
{
    public function __construct(
        protected TaxGroupService $taxGroups
    ) {}

    public function index(): JsonResponse
    {
        $taxGroups = $this->taxGroups->paginate();

        return $this->success(
            TaxGroupResource::collection($taxGroups),
            'Tax groups retrieved successfully.'
        );
    }

    public function store(
        TaxGroupCreateRequest $request
    ): JsonResponse {
        $taxGroup = $this->taxGroups->create(
            $request->validated()
        );

        return $this->success(
            new TaxGroupResource($taxGroup),
            'Tax group created successfully.',
            201
        );
    }

    public function show(
        string $taxGroup
    ): JsonResponse {
        $model = $this->taxGroups->findByUuid(
            $taxGroup
        );

        return $this->success(
            new TaxGroupResource($model),
            'Tax group retrieved successfully.'
        );
    }

    public function update(
        TaxGroupUpdateRequest $request,
        string $taxGroup
    ): JsonResponse {
        $model = $this->taxGroups->findByUuid(
            $taxGroup
        );

        $model = $this->taxGroups->update(
            $model,
            $request->validated()
        );

        return $this->success(
            new TaxGroupResource($model),
            'Tax group updated successfully.'
        );
    }

    public function destroy(
        string $taxGroup
    ): JsonResponse {
        $model = $this->taxGroups->findByUuid(
            $taxGroup
        );

        $this->taxGroups->delete($model);

        return $this->success(
            null,
            'Tax group deleted successfully.'
        );
    }
}