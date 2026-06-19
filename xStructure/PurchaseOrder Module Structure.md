# PurchaseOrder Module

1. bash make-module-repo.sh PurchaseOrder
2. make table name=stock_transfers
3. make table name=stock_transfer_items
4. code App\Modules\PurchaseOrder\Repositories\PurchaseOrderRepository.php
5. code App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface.php
6. code App\Modules\PurchaseOrder\Services\PurchaseOrderService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\PurchaseOrder\Providers\PurchaseOrderServiceProvider::class
9. git commit -m "✅ PurchaseOrder Module Complete (Version 1)"

# Phase 1: PurchaseOrder Module Structure

```bash
PurchaseOrder
├── Controllers
│   └── PurchaseOrderController.php
│
├── Models
│   ├── PurchaseOrder.php
│   └── PurchaseOrderItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── PurchaseOrderRepositoryInterface.php
│   └── PurchaseOrderRepository.php
│
├── Services
│   └── PurchaseOrderService.php
│
├── Requests
│   ├── StorePurchaseOrderRequest.php
│   └── UpdatePurchaseOrderRequest.php
│
├── Resources
│   ├── PurchaseOrderResource.php
│   └── PurchaseOrderItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── PurchaseOrderServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('stock_transfers', function (Blueprint $table) {

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
