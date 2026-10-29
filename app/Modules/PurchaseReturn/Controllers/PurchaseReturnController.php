<?php

namespace App\Modules\PurchaseReturn\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Requests\StorePurchaseReturnRequest;
use App\Modules\PurchaseReturn\Requests\UpdatePurchaseReturnRequest;
use App\Modules\PurchaseReturn\Resources\PurchaseReturnResource;
use App\Modules\PurchaseReturn\Services\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends ApiController
{
    public function __construct(
        protected PurchaseReturnService $service
    ) {}

    protected function resolveReturn(string $idOrUuid): PurchaseReturn
    {
        $return = $this->service->findByUuid($idOrUuid);
        if ($return) {
            return $return;
        }

        if (is_numeric($idOrUuid)) {
            $return = $this->service->find((int) $idOrUuid);
            if ($return) {
                return $return;
            }
        }

        abort(404, 'Purchase return not found.');
    }

    public function index(Request $request): JsonResponse
    {
        $returns = $this->service->paginate(
            $request->only([
                'status',
                'supplier_id',
                'supplier_uuid',
                'purchase_id',
                'purchase_uuid',
                'return_no',
                'return_date_from',
                'return_date_to',
            ]),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            PurchaseReturnResource::collection($returns),
            'Purchase returns retrieved successfully.'
        );
    }

    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $purchaseReturn = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new PurchaseReturnResource($purchaseReturn),
            'Purchase Return created successfully.',
            201
        );
    }

    public function show(string $purchaseReturn): JsonResponse
    {
        $record = $this->resolveReturn($purchaseReturn);

        return $this->success(
            new PurchaseReturnResource($record),
            'Purchase return retrieved successfully.'
        );
    }

    public function update(
        UpdatePurchaseReturnRequest $request,
        string $purchaseReturn
    ): JsonResponse {
        $record = $this->resolveReturn($purchaseReturn);

        $updated = $this->service->update(
            $record,
            $request->validated()
        );

        return $this->success(
            new PurchaseReturnResource($updated),
            'Purchase Return updated successfully.'
        );
    }

    public function approve(string $purchaseReturn): JsonResponse
    {
        $record = $this->resolveReturn($purchaseReturn);
        $confirmed = $this->service->confirm($record);

        return $this->success(
            new PurchaseReturnResource($confirmed),
            'Purchase Return approved successfully.'
        );
    }

    public function confirm(string $purchaseReturn): JsonResponse
    {
        return $this->approve($purchaseReturn);
    }

    public function cancel(Request $request, string $purchaseReturn): JsonResponse
    {
        $record = $this->resolveReturn($purchaseReturn);
        $reason = $request->input('reason', $request->input('cancellation_reason'));
        $cancelled = $this->service->cancel($record, $reason);

        return $this->success(
            new PurchaseReturnResource($cancelled),
            'Purchase Return cancelled successfully.'
        );
    }

    public function destroy(string $purchaseReturn): JsonResponse
    {
        $record = $this->resolveReturn($purchaseReturn);
        $this->service->delete($record);

        return $this->success(
            null,
            'Purchase Return deleted successfully.'
        );
    }
}
