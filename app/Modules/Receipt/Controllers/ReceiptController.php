<?php

namespace App\Modules\Receipt\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\Receipt\Models\Receipt;
use App\Modules\Receipt\Services\ReceiptService;
use App\Modules\Receipt\Resources\ReceiptResource;
use App\Modules\Receipt\Requests\StoreReceiptRequest;

class ReceiptController extends Controller
{
    public function __construct(
        protected ReceiptService $service
    ) {}

    public function index(): JsonResponse
    {
        $receipts =
            $this->service->getAll();

        return response()->json([

            'success' => true,

            'data' => ReceiptResource::collection($receipts),

            'meta' => [

                'current_page' =>
                $receipts->currentPage(),

                'last_page' =>
                $receipts->lastPage(),

                'per_page' =>
                $receipts->perPage(),

                'total' =>
                $receipts->total(),
            ],
        ]);
    }

    public function show(
        Receipt $receipt
    ): JsonResponse {

        return response()->json([

            'success' => true,

            'data' =>
            new ReceiptResource(
                $receipt->load([
                    'customer',
                    'paymentAccount',
                    'advanceAllocations.target',
                ])
            ),
        ]);
    }

    public function store(
        StoreReceiptRequest $request
    ): JsonResponse {

        $receipt =
            $this->service->create(
                $request->validated()
            );

        return response()->json([

            'success' => true,

            'message' =>
            'Receipt created successfully.',

            'data' =>
            new ReceiptResource(
                $receipt
            ),
        ], 201);
    }

    public function update(
        StoreReceiptRequest $request,
        Receipt $receipt
    ): JsonResponse {

        $receipt =
            $this->service->update(
                $receipt->id,
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'message' => 'Receipt updated successfully.',
            'data' => new ReceiptResource(
                $receipt
            ),
        ], 200);
    }

    public function confirm(
        Receipt $receipt
    ): JsonResponse {

        $receipt =
            $this->service->confirm(
                $receipt
            );

        return response()->json([

            'success' => true,

            'message' =>
            'Receipt confirmed successfully.',

            'data' =>
            new ReceiptResource(
                $receipt
            ),
        ]);
    }

    public function cancel(
        Receipt $receipt
    ): JsonResponse {

        $receipt =
            $this->service->cancel(
                $receipt
            );

        return response()->json([

            'success' => true,

            'message' =>
            'Receipt cancelled successfully.',

            'data' =>
            new ReceiptResource(
                $receipt
            ),
        ]);
    }

    public function destroy(
        Receipt $receipt
    ): JsonResponse {

        $this->service->delete(
            $receipt->id
        );

        return response()->json([

            'success' => true,

            'message' =>
            'Receipt deleted successfully.',
        ]);
    }
}
