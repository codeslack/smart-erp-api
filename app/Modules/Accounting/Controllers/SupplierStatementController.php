<?php

namespace App\Modules\Accounting\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Controllers\ApiController;
use App\Modules\Accounting\Requests\SupplierStatementRequest;
use App\Modules\Accounting\Services\SupplierStatementService;
use App\Modules\Accounting\Resources\SupplierStatementResource;
use App\Modules\Supplier\Models\Supplier;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class SupplierStatementController extends ApiController
{
    public function __construct(
        protected SupplierStatementService $service
    ) {}

    /**
     * SupplierStatement
     */
    public function show(
        SupplierStatementRequest $request,
        string $supplierId
    ): JsonResponse {
        $supplier = Supplier::query()
            ->where(function ($q) use ($supplierId) {
                if (Str::isUuid($supplierId)) {
                    $q->where('uuid', $supplierId);
                } else {
                    $q->whereKey($supplierId);
                }
            })
            ->firstOrFail();

        $statement = $this->service->statement(
            supplierId: $supplier->id,
            fromDate: $request->validated('from_date'),
            toDate: $request->validated('to_date')
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier Statement fetched successfully',
            'data' => [
                'supplier' => $statement['supplier'],
                'opening_balance' => $statement['opening_balance'],
                'outstanding_balance' => $statement['outstanding_balance'],
                'transactions' => SupplierStatementResource::collection(
                    collect($statement['transactions'])
                ),
            ],
        ]);
    }
}
