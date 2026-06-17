<?php

namespace App\Modules\PurchaseReturn\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Services\PurchaseReturnService;
use App\Modules\PurchaseReturn\Resources\PurchaseReturnResource;
use App\Modules\PurchaseReturn\Requests\StorePurchaseReturnRequest;
use App\Modules\PurchaseReturn\Requests\UpdatePurchaseReturnRequest;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected PurchaseReturnService $service
    ) {}

    public function index()
    {
        return PurchaseReturnResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StorePurchaseReturnRequest $request
    ) {
        return new PurchaseReturnResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        PurchaseReturn $purchaseReturn
    ) {
        return new PurchaseReturnResource(
            $this->service->find(
                $purchaseReturn->id
            )
        );
    }

    public function update(
        UpdatePurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn
    ) {
        return new PurchaseReturnResource(
            $this->service->update(
                $purchaseReturn->id,
                $request->validated()
            )
        );
    }

    public function approve(
        PurchaseReturn $purchaseReturn
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Purchase Return approved successfully',
            'data' => new PurchaseReturnResource(
                $this->service->approve(
                    $purchaseReturn
                )
            ),
        ]);
    }

    public function destroy(
        PurchaseReturn $purchaseReturn
    ) {
        $this->service->delete(
            $purchaseReturn->id
        );

        return response()->json([
            'message' => 'Purchase Return deleted successfully',
        ]);
    }
}
