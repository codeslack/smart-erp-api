# Customer Module

1. bash make-module.sh Customer
2. make table name=customers
3. code App\Modules\Customer\Repositories\CustomerRepository.php
4. code App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface.php
5. code App\Modules\Customer\Services\CustomerService.php
6. make db-up
6. code bootstrap\providers.php
    |-App\Modules\Customer\Providers\CustomerServiceProvider::class
7. git commit -m "✅ Customer Module Complete (Version 1)"

Customer
├── Model
├── Migration
├── RepositoryInterface
├── Repository
├── Service
├── Controller
├── Resource
├── StoreRequest
├── UpdateRequest
├── Routes
└── Provider

# Phase 1: Customer Module Structure

```bash
Customer
├── Controllers
│   └── CustomerController.php
│
├── Models
│   └── Customer.php
│
├── Repositories
│   ├── Contracts
│   │   └── CustomerRepositoryInterface.php
│   └── CustomerRepository.php
│
├── Services
│   └── CustomerService.php
│
├── Requests
│   ├── StoreCustomerRequest.php
│   └── UpdateCustomerRequest.php
│
├── Resources
│   └── CustomerResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── CustomerServiceProvider.php
```

# Step 1: Create Migrations

````bash
Schema::create('customers', function (Blueprint $table) {

    $table->id();

    $table->foreignId('tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();

    $table->string('name');

    $table->string('code')
        ->nullable();

    $table->string('contact_person')
        ->nullable();

    $table->string('phone')
        ->nullable();

    $table->string('email')
        ->nullable();

    $table->text('address')
        ->nullable();

    $table->string('tax_number')
        ->nullable();

    $table->boolean('is_active')
        ->default(true);

    $table->timestamps();

    $table->index([
        'tenant_id',
        'name'
    ]);
});