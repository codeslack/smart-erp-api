<?php

namespace App\Modules\Unit\Controllers;

use App\Modules\Unit\Models\Unit;
use App\Http\Controllers\Controller;
use App\Modules\Unit\Resources\UnitResource;
use App\Modules\Unit\Requests\StoreUnitRequest;
use App\Modules\Unit\Requests\UpdateUnitRequest;

class UnitController extends Controller
{
    public function index()
    {
        return UnitResource::collection(
            Unit::orderBy('name')->get()
        );
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = Unit::create(
            $request->validated()
        );

        return new UnitResource($unit);
    }

    public function show(Unit $unit)
    {
        return new UnitResource($unit);
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit
    ) {
        $unit->update(
            $request->validated()
        );

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully'
        ]);
    }
}