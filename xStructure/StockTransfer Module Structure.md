# StockTransfer Module

1. bash make-module.sh StockTransfer
2. make table name=stock_transfers
3. make table name=stock_transfer_items
4. code App\Modules\StockTransfer\Repositories\StockTransferRepository.php
5. code App\Modules\StockTransfer\Repositories\Contracts\StockTransferRepositoryInterface.php
6. code App\Modules\StockTransfer\Services\StockTransferService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\StockTransfer\Providers\StockTransferServiceProvider::class
9. git commit -m "✅ StockTransfer Module Complete (Version 1)"

# Phase 1: StockTransfer Module Structure

```bash
StockTransfer
├── Controllers
│   └── StockTransferController.php
│
├── Models
│   ├── StockTransfer.php
│   └── StockTransferItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── StockTransferRepositoryInterface.php
│   └── StockTransferRepository.php
│
├── Services
│   └── StockTransferService.php
│
├── Requests
│   ├── StoreStockTransferRequest.php
│   └── UpdateStockTransferRequest.php
│
├── Resources
│   ├── StockTransferResource.php
│   └── StockTransferItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── StockTransferServiceProvider.php
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
