<?php

namespace App\Modules\Customer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerService;
use App\Modules\Customer\Resources\CustomerResource;
use App\Modules\Customer\Requests\StoreCustomerRequest;
use App\Modules\Customer\Requests\UpdateCustomerRequest;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $service
    ) {}

    public function index()
    {
        return CustomerResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreCustomerRequest $request
    ) {
        return new CustomerResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        Customer $customer
    ) {
        return new CustomerResource(
            $this->service->find(
                $customer->id
            )
        );
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ) {
        return new CustomerResource(
            $this->service->update(
                $customer->id,
                $request->validated()
            )
        );
    }

    public function destroy(
        Customer $customer
    ) {
        $this->service->delete(
            $customer->id
        );

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }
}
