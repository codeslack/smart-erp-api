<?php

namespace App\Modules\Tax\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Tax\Requests\TaxApplicabilityRuleCreateRequest;
use App\Modules\Tax\Requests\TaxApplicabilityRuleUpdateRequest;

use App\Modules\Tax\Resources\TaxApplicabilityRuleResource;

use App\Modules\Tax\Services\TaxApplicabilityRuleService;

class TaxApplicabilityRuleController extends ApiController
{
    public function __construct(
        protected TaxApplicabilityRuleService $taxApplicabilityRules
    ) {}

    public function index(): JsonResponse
    {
        $taxApplicabilityRules =
            $this->taxApplicabilityRules->paginate();

        return $this->success(
            TaxApplicabilityRuleResource::collection(
                $taxApplicabilityRules
            ),
            'Tax applicability rules retrieved successfully.'
        );
    }

    public function store(
        TaxApplicabilityRuleCreateRequest $request
    ): JsonResponse {
        $taxApplicabilityRule =
            $this->taxApplicabilityRules->create(
                $request->validated()
            );

        return $this->success(
            new TaxApplicabilityRuleResource(
                $taxApplicabilityRule
            ),
            'Tax applicability rule created successfully.',
            201
        );
    }

    public function show(
        string $taxApplicabilityRule
    ): JsonResponse {
        $model =
            $this->taxApplicabilityRules->findByUuid(
                $taxApplicabilityRule
            );

        return $this->success(
            new TaxApplicabilityRuleResource($model),
            'Tax applicability rule retrieved successfully.'
        );
    }

    public function update(
        TaxApplicabilityRuleUpdateRequest $request,
        string $taxApplicabilityRule
    ): JsonResponse {
        $model =
            $this->taxApplicabilityRules->findByUuid(
                $taxApplicabilityRule
            );

        $model =
            $this->taxApplicabilityRules->update(
                $model,
                $request->validated()
            );

        return $this->success(
            new TaxApplicabilityRuleResource($model),
            'Tax applicability rule updated successfully.'
        );
    }

    public function destroy(
        string $taxApplicabilityRule
    ): JsonResponse {
        $model =
            $this->taxApplicabilityRules->findByUuid(
                $taxApplicabilityRule
            );

        $this->taxApplicabilityRules->delete($model);

        return $this->success(
            null,
            'Tax applicability rule deleted successfully.'
        );
    }
}