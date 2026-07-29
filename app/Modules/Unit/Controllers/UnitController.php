<?php

namespace App\Modules\Unit\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Unit\Models\Unit;

use App\Modules\Unit\Services\UnitService;

use App\Modules\Unit\Resources\UnitResource;

use App\Modules\Unit\Requests\StoreUnitRequest;
use App\Modules\Unit\Requests\UpdateUnitRequest;

class UnitController extends ApiController
{
    public function __construct(
        protected UnitService $service
    ) {}

    public function index()
    {
        $units = $this->service
            ->paginate();

        return $this->success(
            UnitResource::collection(
                $units
            ),
            'Units retrieved successfully.'
        );
    }

    public function store(
        StoreUnitRequest $request
    )
    {
        $unit = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new UnitResource(
                $unit
            ),
            'Unit created successfully.',
            201
        );
    }

    public function show(
        Unit $unit
    )
    {
        return $this->success(
            new UnitResource(
                $unit
            ),
            'Unit retrieved successfully.'
        );
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit
    )
    {
        $unit = $this->service->update(
            $unit,
            $request->validated()
        );

        return $this->success(
            new UnitResource(
                $unit
            ),
            'Unit updated successfully.'
        );
    }

    public function destroy(
        Unit $unit
    )
    {
        $this->service->delete(
            $unit
        );

        return $this->success(
            null,
            'Unit deleted successfully.'
        );
    }
}