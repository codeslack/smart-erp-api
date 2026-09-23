<?php

namespace App\Modules\PaymentTerm\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\PaymentTerm\Models\PaymentTerm;

use App\Modules\PaymentTerm\Services\PaymentTermService;

use App\Modules\PaymentTerm\Resources\PaymentTermResource;

use App\Modules\PaymentTerm\Requests\StorePaymentTermRequest;
use App\Modules\PaymentTerm\Requests\UpdatePaymentTermRequest;

class PaymentTermController extends ApiController
{
    public function __construct(
        protected PaymentTermService $service
    ) {}

    /**
     * List Payment Terms
     *
     * Retrieves a paginated list of payment terms.
     */

    public function index()
    {
        $paymentTerms = $this->service
            ->paginate();

        return $this->success(
            PaymentTermResource::collection(
                $paymentTerms
            ),
            'Payment terms retrieved successfully.'
        );
    }

    /**
     * Create Payment Term
     *
     * Creates a new payment term.
     */

    public function store(
        StorePaymentTermRequest $request
    )
    {
        $paymentTerm = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new PaymentTermResource(
                $paymentTerm
            ),
            'Payment term created successfully.',
            201
        );
    }

    /**
     * Show Payment Term
     *
     * Display a specific payment term.
     */

    public function show(
        PaymentTerm $paymentTerm
    )
    {
        return $this->success(
            new PaymentTermResource(
                $paymentTerm
            ),
            'Payment term retrieved successfully.'
        );
    }

    /**
     * UpdatePayment Term
     *
     * Update an existing payment term.
     */

    public function update(
        UpdatePaymentTermRequest $request,
        PaymentTerm $paymentTerm
    )
    {
        $paymentTerm = $this->service->update(
            $paymentTerm,
            $request->validated()
        );

        return $this->success(
            new PaymentTermResource(
                $paymentTerm
            ),
            'Payment term updated successfully.'
        );
    }

    /**
     * Delete Payment Term
     *
     * Deletes a specific payment term.
     */

    public function destroy(
        PaymentTerm $paymentTerm
    )
    {
        $this->service->delete(
            $paymentTerm
        );

        return $this->success(
            null,
            'Payment term deleted successfully.'
        );
    }
}