<?php

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\Settings\Requests\UpdateSettingRequest;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends ApiController
{
    public function __construct(
        protected SettingService $service
    ) {
    }

    public function show(
        string $group
    ): JsonResponse {

        return $this->success(
            $this->service->getGroup(
                $group
            )
        );
    }

    public function update(
        UpdateSettingRequest $request,
        string $group
    ): JsonResponse {

        $this->service->updateGroup(
            $group,
            $request->validated()
        );

        return $this->success(
            $this->service->getGroup(
                $group
            ),
            'Settings updated successfully.'
        );
    }
}