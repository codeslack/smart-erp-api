<?php

namespace App\Modules\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Purchase\Resources\PurchaseResource;
use App\Modules\Purchase\Requests\StorePurchaseRequest;
use App\Modules\Purchase\Requests\UpdatePurchaseRequest;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $service
    ) {}

    public function index()
    {
        return PurchaseResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StorePurchaseRequest $request
    ) {
        return new PurchaseResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        Purchase $purchase
    ) {
        return new PurchaseResource(
            $this->service->find(
                $purchase->id
            )
        );
    }

    public function update(
        UpdatePurchaseRequest $request,
        Purchase $purchase
    ) {
        return new PurchaseResource(
            $this->service->update(
                $purchase->id,
                $request->validated()
            )
        );
    }

    public function approve(
        Purchase $purchase
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Purchase approved successfully',
            'data' => new PurchaseResource(
                $this->service->approve(
                    $purchase
                )
            ),
        ]);
    }

    public function destroy(
        Purchase $purchase
    ) {
        $this->service->delete(
            $purchase->id
        );

        return response()->json([
            'message' => 'Purchase deleted successfully',
        ]);
    }
}
