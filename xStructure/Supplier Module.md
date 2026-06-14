# Supplier Module
1. bash make-module.sh Supplier
2. make table name=product_stocks
3. code App\Modules\Supplier\Repositories\SupplierRepository.php
4. code App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface.php
5. code App\Modules\Supplier\Services\SupplierService.php
6. code App\Modules\Supplier\Services\SupplierService.php

Supplier
├── Model
├── Migration
├── Repository
├── Service
├── Controller
├── Resource
├── Requests
└── Routes

# Phase 1: Supplier Module Structure

```bash
Modules
└── Supplier
    ├── Controllers
    │   └── SupplierController.php
    │
    ├── Models
    │   ├── ProductStock.php
    │   └── StockLedger.php
    │
    ├── Repositories
    │   ├── Contracts
    │   │   └── SupplierRepositoryInterface.php
    │   └── SupplierRepository.php
    │
    ├── Services
    │   └── SupplierService.php
    │
    ├── Requests
    │   └── OpeningStockRequest.php
    │
    ├── Resources
    │   └── StockLedgerResource.php
    │
    ├── Routes
    │   └── api.php
    │
    └── Providers
        └── SupplierServiceProvider.php
```

# Step 1: Create Migrations

**_ product_stocks _**

````bash
Schema::create('product_stocks', function (Blueprint $table) {

    $table->id();

    $table->foreignId('tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();

    $table->foreignId('product_id')
        ->constrained('products')
        ->cascadeOnDelete();

    $table->foreignId('warehouse_id')
        ->constrained('warehouses')
        ->cascadeOnDelete();

    $table->decimal('quantity', 18, 4)
        ->default(0);

    $table->timestamps();

    $table->unique([
        'tenant_id',
        'product_id',
        'warehouse_id'
    ]);
});

*** stock_ledgers ***
```bash
Schema::create('stock_ledgers', function (Blueprint $table) {

    $table->id();

    $table->foreignId('tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();

    $table->foreignId('product_id')
        ->constrained('products')
        ->cascadeOnDelete();

    $table->foreignId('warehouse_id')
        ->constrained('warehouses')
        ->cascadeOnDelete();

    $table->string('transaction_type');

    $table->string('reference_type')
        ->nullable();

    $table->unsignedBigInteger('reference_id')
        ->nullable();

    $table->decimal('qty_in', 18, 4)
        ->default(0);

    $table->decimal('qty_out', 18, 4)
        ->default(0);

    $table->decimal('balance_after', 18, 4)
        ->default(0);

    $table->text('remarks')
        ->nullable();

    $table->timestamps();
});


Step 2: Models
ProductStock
namespace App\Modules\Supplier\Models;

use App\Core\Tenant\Models\TenantModel;

class ProductStock extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'quantity',
    ];
}


````
