# StockAdjustment Module

1. bash make-module.sh StockAdjustment
2. make table name=stock_adjustments
3. make table name=stock_adjustment_items
4. code App\Modules\StockAdjustment\Repositories\StockAdjustmentRepository.php
5. code App\Modules\StockAdjustment\Repositories\Contracts\StockAdjustmentRepositoryInterface.php
6. code App\Modules\StockAdjustment\Services\StockAdjustmentService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\StockAdjustment\Providers\StockAdjustmentServiceProvider::class
9. git commit -m "✅ StockAdjustment Module Complete (Version 1)"

# Phase 1: StockAdjustment Module Structure

```bash
StockAdjustment
├── Controllers
│   └── StockAdjustmentController.php
│
├── Models
│   ├── StockAdjustment.php
│   └── StockAdjustmentItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── StockAdjustmentRepositoryInterface.php
│   └── StockAdjustmentRepository.php
│
├── Services
│   └── StockAdjustmentService.php
│
├── Requests
│   ├── StoreStockAdjustmentRequest.php
│   └── UpdateStockAdjustmentRequest.php
│
├── Resources
│   ├── StockAdjustmentResource.php
│   └── StockAdjustmentItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── StockAdjustmentServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('stock_adjustments', function (Blueprint $table) {

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
