<?php

namespace App\Modules\Warehouse\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Resources\WarehouseResource;
use App\Modules\Warehouse\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseRequest;

class WarehouseController extends Controller
{
    public function index()
    {
        return WarehouseResource::collection(
            Warehouse::latest()->paginate()
        );
    }

    public function store(StoreWarehouseRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ]);
    }
}
