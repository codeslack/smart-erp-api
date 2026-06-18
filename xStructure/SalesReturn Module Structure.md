# SalesReturn Module

1. bash make-module.sh SalesReturn
2. make table name=sales_returns
2. make table name=sales_return_items
3. code App\Modules\SalesReturn\Repositories\SalesReturnRepository.php
4. code App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface.php
5. code App\Modules\SalesReturn\Services\SalesReturnService.php
6. make db-up
7. code bootstrap\providers.php
   |-App\Modules\SalesReturn\Providers\SalesReturnServiceProvider::class
8. git commit -m "✅ SalesReturn Module Complete (Version 1)"

# Phase 1: SalesReturn Module Structure

```bash
SalesReturn
├── Controllers
│   └── SalesReturnController.php
│
├── Models
│   ├── SalesReturn.php
│   └── SalesReturnItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── SalesReturnRepositoryInterface.php
│   └── SalesReturnRepository.php
│
├── Services
│   └── SalesReturnService.php
│
├── Requests
│   ├── StoreSalesReturnRequest.php
│   └── UpdateSalesReturnRequest.php
│
├── Resources
│   ├── SalesReturnResource.php
│   └── SalesReturnItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── SalesReturnServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('sales_returns', function (Blueprint $table) {

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
```
