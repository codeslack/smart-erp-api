<?php

namespace App\Modules\Area\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Area\Models\Area;

use App\Modules\Area\Services\AreaService;

use App\Modules\Area\Resources\AreaResource;

use App\Modules\Area\Requests\StoreAreaRequest;
use App\Modules\Area\Requests\UpdateAreaRequest;

class AreaController extends ApiController
{
    public function __construct(
        protected AreaService $service
    ) {}

    public function index()
    {
        $areas = $this->service
            ->paginate();

        return $this->success(
            AreaResource::collection(
                $areas
            ),
            'Areas retrieved successfully.'
        );
    }

    public function store(
        StoreAreaRequest $request
    )
    {
        $area = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new AreaResource(
                $area
            ),
            'Area created successfully.',
            201
        );
    }

    public function show(
        Area $area
    )
    {
        return $this->success(
            new AreaResource(
                $area
            ),
            'Area retrieved successfully.'
        );
    }

    public function update(
        UpdateAreaRequest $request,
        Area $area
    )
    {
        $area = $this->service->update(
            $area,
            $request->validated()
        );

        return $this->success(
            new AreaResource(
                $area
            ),
            'Area updated successfully.'
        );
    }

    public function destroy(
        Area $area
    )
    {
        $this->service->delete(
            $area
        );

        return $this->success(
            null,
            'Area deleted successfully.'
        );
    }
}