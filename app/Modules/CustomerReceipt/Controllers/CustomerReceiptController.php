<?php

namespace App\Modules\CustomerReceipt\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Services\CustomerReceiptService;
use App\Modules\CustomerReceipt\Resources\CustomerReceiptResource;
use App\Modules\CustomerReceipt\Requests\StoreCustomerReceiptRequest;

class CustomerReceiptController extends Controller
{
    public function __construct(
        protected CustomerReceiptService $service
    ) {}

    public function index(): JsonResponse
    {
        $receipts =
            $this->service->getAll();

        return response()->json([

            'success' => true,

            'data' => CustomerReceiptResource::collection( $receipts ),

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
        CustomerReceipt $customerReceipt
    ): JsonResponse {

        return response()->json([

            'success' => true,

            'data' =>
                new CustomerReceiptResource(
                    $customerReceipt->load([
                        'customer',
                        'paymentAccount',
                        'advanceAllocations.target',
                    ])
                ),
        ]);
    }

    public function store(
        StoreCustomerReceiptRequest $request
    ): JsonResponse {

        $receipt =
            $this->service->create(
                $request->validated()
            );

        return response()->json([

            'success' => true,

            'message' =>
                'Customer receipt created successfully.',

            'data' =>
                new CustomerReceiptResource(
                    $receipt
                ),
        ], 201);
    }

    public function update(
        StoreCustomerReceiptRequest $request,
        CustomerReceipt $customerReceipt
    ): JsonResponse {

        $receipt =
            $this->service->update(
                $customerReceipt->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Customer receipt updated successfully.',
                'data' => new CustomerReceiptResource(
                    $receipt
                ),
            ], 200);
    }

    public function confirm(
        CustomerReceipt $customerReceipt
    ): JsonResponse {

        $receipt =
            $this->service->confirm(
                $customerReceipt
            );

        return response()->json([

            'success' => true,

            'message' =>
                'Customer receipt confirmed successfully.',

            'data' =>
                new CustomerReceiptResource(
                    $receipt
                ),
        ]);
    }

    public function cancel(
        CustomerReceipt $customerReceipt
    ): JsonResponse {

        $receipt =
            $this->service->cancel(
                $customerReceipt
            );

        return response()->json([

            'success' => true,

            'message' =>
                'Customer receipt cancelled successfully.',

            'data' =>
                new CustomerReceiptResource(
                    $receipt
                ),
        ]);
    }

    public function destroy(
        CustomerReceipt $customerReceipt
    ): JsonResponse {

        $this->service->delete(
            $customerReceipt->id
        );

        return response()->json([

            'success' => true,

            'message' =>
                'Customer receipt deleted successfully.',
        ]);
    }
}