<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Controllers\ApiController;
use App\Modules\Accounting\Requests\CustomerStatementRequest;
use App\Modules\Accounting\Resources\CustomerStatementResource;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\Customer\Models\Customer;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class CustomerStatementController extends ApiController
{
    public function __construct(
        protected CustomerStatementService $service
    ) {}

    /**
     * CustomerStatement
     */
    public function show(
        CustomerStatementRequest $request,
        string $customerId
    ): JsonResponse {
        $customer = Customer::query()
            ->where(function ($q) use ($customerId) {
                if (Str::isUuid($customerId)) {
                    $q->where('uuid', $customerId);
                } else {
                    $q->whereKey($customerId);
                }
            })
            ->firstOrFail();

        $statement = $this->service->getStatement(
            customerId: $customer->id,
            fromDate: $request->validated('from_date'),
            toDate: $request->validated('to_date')
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer Statement fetched successfully',
            'data' => [
                'customer' => $statement['customer'],
                'opening_balance' => $statement['opening_balance'],
                'outstanding_balance' => $statement['outstanding_balance'],
                'transactions' => CustomerStatementResource::collection(
                    collect($statement['transactions'])
                ),
            ],
        ]);
    }
}
