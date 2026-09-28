<?php

namespace App\Modules\Payment\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Payment\Requests\StorePaymentRequest;
use App\Modules\Payment\Requests\UpdatePaymentRequest;

use App\Modules\Payment\Resources\PaymentResource;

use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;

class PaymentController extends ApiController
{
    public function __construct(
        protected PaymentServiceInterface $payments
    ) {}

    public function index(): JsonResponse
    {
        $payments = $this->payments->paginate();

        return $this->success(
            PaymentResource::collection($payments),
            'Payments retrieved successfully.'
        );
    }

    public function store(
        StorePaymentRequest $request
    ): JsonResponse {
        $payment = $this->payments->create(
            $request->validated()
        );

        return $this->success(
            new PaymentResource($payment),
            'Payment created successfully.',
            201
        );
    }

    public function show(
        string $payment
    ): JsonResponse {
        $model = $this->payments->findByUuid(
            $payment
        );

        return $this->success(
            new PaymentResource($model),
            'Payment retrieved successfully.'
        );
    }

    public function update(
        UpdatePaymentRequest $request,
        string $payment
    ): JsonResponse {
        $model = $this->payments->findByUuid(
            $payment
        );

        $model = $this->payments->update(
            $model,
            $request->validated()
        );

        return $this->success(
            new PaymentResource($model),
            'Payment updated successfully.'
        );
    }

    public function destroy(
        string $payment
    ): JsonResponse {
        $model = $this->payments->findByUuid(
            $payment
        );

        $this->payments->delete($model);

        return $this->success(
            null,
            'Payment deleted successfully.'
        );
    }
}