<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxCreateRequest;
use App\Modules\Tax\Requests\TaxUpdateRequest;

use App\Modules\Tax\Resources\TaxResource;

use App\Modules\Tax\Services\TaxService;

class TaxController extends ApiController
{
    public function __construct(
        protected TaxService $service
    ) {}

    public function index(): JsonResponse
    {
        $taxes = $this->service->paginate();

        return $this->success(
            TaxResource::collection($taxes),
            'Taxes retrieved successfully.'
        );
    }

    public function store(
        TaxCreateRequest $request
    ): JsonResponse {
        $tax = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new TaxResource($tax),
            'Tax created successfully.',
            201
        );
    }

    public function show(
        string $tax
    ): JsonResponse {
        $model = $this->service->findByUuid(
            $tax
        );

        if ($model === null) {
            abort(404);
        }

        return $this->success(
            new TaxResource($model),
            'Tax retrieved successfully.'
        );
    }

    public function update(
        TaxUpdateRequest $request,
        string $tax
    ): JsonResponse {
        $model = $this->service->findByUuid(
            $tax
        );

        if ($model === null) {
            abort(404);
        }

        $model = $this->service->update(
            $model,
            $request->validated()
        );

        return $this->success(
            new TaxResource($model),
            'Tax updated successfully.'
        );
    }

    public function destroy(
        string $tax
    ): JsonResponse {
        $model = $this->service->findByUuid(
            $tax
        );

        if ($model === null) {
            abort(404);
        }

        $this->service->delete($model);

        return $this->success(
            null,
            'Tax deleted successfully.'
        );
    }
}