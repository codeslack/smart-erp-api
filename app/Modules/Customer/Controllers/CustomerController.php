<?php

namespace App\Modules\Customer\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Customer\Models\Customer;

use App\Modules\Customer\Services\CustomerService;

use App\Modules\Customer\Resources\CustomerResource;

use App\Modules\Customer\Requests\StoreCustomerRequest;
use App\Modules\Customer\Requests\UpdateCustomerRequest;

class CustomerController extends ApiController
{
    public function __construct(
        protected CustomerService $service
    ) {}

    public function index()
    {
        $customers = $this->service->paginate();

        return $this->success(
            CustomerResource::collection($customers),
            'Customers retrieved successfully.'
        );
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new CustomerResource($customer),
            'Customer created successfully.',
            201
        );
    }

    public function show(Customer $customer)
    {
        $customer = $this->service->findByUuid(
            $customer->uuid
        );

        return $this->success(
            new CustomerResource($customer),
            'Customer retrieved successfully.'
        );
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ) {
        $customer = $this->service->update(
            $customer,
            $request->validated()
        );

        return $this->success(
            new CustomerResource($customer),
            'Customer updated successfully.'
        );
    }

    public function destroy(Customer $customer)
    {
        $this->service->delete($customer);

        return $this->success(
            null,
            'Customer deleted successfully.'
        );
    }
}
