<?php

namespace App\Modules\SupplierPayment\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Services\SupplierPaymentService;
use App\Modules\SupplierPayment\Resources\SupplierPaymentResource;
use App\Modules\SupplierPayment\Requests\StoreSupplierPaymentRequest;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $service
    ) {}

    public function index(): JsonResponse
    {
        $payments =
            $this->service->getAll();

        return response()->json([
            'success' => true,

            'data' => SupplierPaymentResource::collection( $payments ),
            
            'meta' => [

                'current_page' =>
                    $payments->currentPage(),

                'last_page' =>
                    $payments->lastPage(),

                'per_page' =>
                    $payments->perPage(),

                'total' =>
                    $payments->total(),
            ],
        ]);
    }

    public function show(
        SupplierPayment $supplierPayment
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' => 
                new SupplierPaymentResource(
                    $supplierPayment->load([
                        'supplier',
                        'paymentAccount',
                        'advanceAllocations.target'
                    ])
                ),
        ]);    
    }

    public function store(
        StoreSupplierPaymentRequest $request
    ): JsonResponse {

        $payment =
            $this->service->create(
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'message' => 'Supplier payment created successfully.',
            'data' => new SupplierPaymentResource(
                $payment
            ),
        ], 201);
    }

    public function update(
        StoreSupplierPaymentRequest $request,
        SupplierPayment $supplierPayment
    ): JsonResponse {

        $payment =
            $this->service->update(
                $supplierPayment->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Supplier payment updated successfully.',
                'data' => new SupplierPaymentResource(
                    $payment
                ),
            ], 200);
    }

    public function confirm(
        SupplierPayment $supplierPayment
    ): JsonResponse {

        $payment =
            $this->service->confirm(
                $supplierPayment
            );

        return response()->json([
            'success' => true,
            'message' => 'Supplier payment confirmed successfully.',
            'data' => new SupplierPaymentResource(
                $payment
            ),
        ], 200);
    }

    public function cancel(
        SupplierPayment $supplierPayment
    ): JsonResponse {

        $payment =
            $this->service->cancel(
                $supplierPayment
            );

        return response()->json([
            'success' => true,
            'message' => 'Supplier payment cancelled successfully.',
            'data'    => new SupplierPaymentResource($payment),
        ], 200);
    }

    public function destroy(
        SupplierPayment $supplierPayment
    ): JsonResponse {

        $this->service->delete(
            $supplierPayment->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier payment deleted successfully.',
        ]);
    }
}
