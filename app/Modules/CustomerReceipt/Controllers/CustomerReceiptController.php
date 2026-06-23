<?php

namespace App\Modules\CustomerReceipt\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Services\CustomerReceiptService;
use App\Modules\CustomerReceipt\Requests\CustomerReceiptRequest;
use App\Modules\CustomerReceipt\Resources\CustomerReceiptResource;

class CustomerReceiptController extends Controller
{
    public function __construct(
        protected CustomerReceiptService $service
    ) {}

    public function index()
    {
        return CustomerReceiptResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        CustomerReceiptRequest $request
    ) {
        $receipt = $this->service->create(
            $request->validated()
        );

        return response()->json([

            'success' => true,

            'message' => 'Customer Receipt created successfully',

            'data' => new CustomerReceiptResource(
                $receipt
            ),

        ], 201);
    }

    public function show(
        CustomerReceipt $customerReceipt
    ) {
        return new CustomerReceiptResource(
            $this->service->find(
                $customerReceipt->id
            )
        );
    }

    public function confirm(
        CustomerReceipt $customerReceipt
    ) {
        $receipt = $this->service->confirm(
            $customerReceipt
        );

        return response()->json([

            'success' => true,

            'message' => 'Customer Receipt confirmed successfully',

            'data' => new CustomerReceiptResource(
                $receipt
            ),

        ]);
    }

    public function cancel(
        CustomerReceipt $customerReceipt
    ) {
        $receipt = $this->service->cancel(
            $customerReceipt
        );

        return response()->json([

            'success' => true,

            'message' => 'Customer Receipt cancelled successfully',

            'data' => new CustomerReceiptResource(
                $receipt
            ),

        ]);
    }

    public function destroy(
        CustomerReceipt $customerReceipt
    ) {
        $this->service->delete(
            $customerReceipt->id
        );

        return response()->json([

            'success' => true,

            'message' => 'Customer Receipt deleted successfully',

        ]);
    }
}
