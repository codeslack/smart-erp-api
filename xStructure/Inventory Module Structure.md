# Inventory Module

1. bash make-module.sh Inventory
2. make table name=product_stocks
3. code App\Modules\Inventory\Repositories\InventoryRepository.php
4. code App\Modules\Inventory\Repositories\Contracts\InventoryRepositoryInterface.php
5. code App\Modules\Inventory\Services\InventoryService.php
6. code App\Modules\Inventory\Services\InventoryService.php
7. git commit -m "✅ Inventory Module Complete (Version 1)"

Inventory
├── Controllers
├── Models
├── Requests
├── Resources
├── Routes
├── Services
├── Repositories
├── Contracts
└── Providers

Inventory Module
├── ProductStock Model
├── StockLedger Model
├── Product Stocks Migration
├── Stock Ledgers Migration
├── OpeningStockRequest
├── InventoryService
├── InventoryController
└── Opening Stock API

# Phase 1: Inventory Module Structure

```bash
Modules
└── Inventory
    ├── Controllers
    │   └── InventoryController.php
    │
    ├── Models
    │   ├── ProductStock.php
    │   └── StockLedger.php
    │
    ├── Repositories
    │   ├── Contracts
    │   │   └── InventoryRepositoryInterface.php
    │   └── InventoryRepository.php
    │
    ├── Services
    │   └── InventoryService.php
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
        └── InventoryServiceProvider.php
```

# Step 1: Create Migrations
*** product_stocks ***
```bash
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
namespace App\Modules\Inventory\Models;

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

