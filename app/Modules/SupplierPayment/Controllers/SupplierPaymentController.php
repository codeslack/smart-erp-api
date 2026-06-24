<?php

namespace App\Modules\SupplierPayment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Services\SupplierPaymentService;
use App\Modules\SupplierPayment\Requests\StoreSupplierPaymentRequest;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $service
    ) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getAll(),
        ]);
    }

    public function show(
        SupplierPayment $supplierPayment
    ) {
        return response()->json([
            'success' => true,
            'data' => $supplierPayment,
        ]);
    }

    public function store(
        StoreSupplierPaymentRequest $request
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Supplier Payment created successfully',
            'data' => $this->service->create(
                $request->validated()
            ),
        ]);
    }

    public function confirm(
        SupplierPayment $supplierPayment
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Supplier Payment confirmed successfully',
            'data' => $this->service->confirm(
                $supplierPayment
            ),
        ]);
    }

    public function cancel(
        SupplierPayment $supplierPayment
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Supplier Payment cancelled successfully',
            'data' => $this->service->cancel(
                $supplierPayment
            ),
        ]);
    }

    public function destroy(
        SupplierPayment $supplierPayment
    ) {
        $this->service->delete(
            $supplierPayment->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier Payment deleted successfully',
        ]);
    }
}
