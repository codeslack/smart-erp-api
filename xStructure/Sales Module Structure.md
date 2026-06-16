# Sales Module

1. bash make-module.sh Sale
2. make table name=sales
2. make table name=sale_items
3. code App\Modules\Sale\Repositories\SaleRepository.php
4. code App\Modules\Sale\Repositories\Contracts\SaleRepositoryInterface.php
5. code App\Modules\Sale\Services\SaleService.php
6. make db-up
7. code bootstrap\providers.php
   |-App\Modules\Sale\Providers\SaleServiceProvider::class
8. git commit -m "✅ Sale Module Complete (Version 1)"

# Phase 1: Sale Module Structure

```bash
Sales
├── Enums
│   └── SaleStatus.php
│
├── Models
│   ├── Sale.php
│   └── SaleItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── SaleRepositoryInterface.php
│   └── SaleRepository.php
│
├── Services
│   └── SaleService.php
│
├── Controllers
│   └── SaleController.php
│
├── Requests
│   ├── StoreSaleRequest.php
│   └── UpdateSaleRequest.php
│
├── Resources
│   ├── SaleResource.php
│   └── SaleItemResource.php
│
├── Routes
│   └── api.php
---------------------------------------
Sale
├── Controllers
│   └── SaleController.php
│
├── Models
│   ├── Sale.php
│   └── SaleItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── SaleRepositoryInterface.php
│   └── SaleRepository.php
│
├── Services
│   └── SaleService.php
│
├── Requests
│   ├── StoreSaleRequest.php
│   └── UpdateSaleRequest.php
│
├── Resources
│   ├── SaleResource.php
│   └── SaleItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── SaleServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('sales', function (Blueprint $table) {

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
