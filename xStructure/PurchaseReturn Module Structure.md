# PurchaseReturn Module

1. bash make-module.sh PurchaseReturn
2. make table name=purchase_returns
3. code App\Modules\PurchaseReturn\Repositories\PurchaseReturnRepository.php
4. code App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface.php
5. code App\Modules\PurchaseReturn\Services\PurchaseReturnService.php
6. make db-up
7. code bootstrap\providers.php
   |-App\Modules\PurchaseReturn\Providers\PurchaseReturnServiceProvider::class
8. git commit -m "✅ PurchaseReturn Module Complete (Version 1)"

# Phase 1: PurchaseReturn Module Structure

```bash
PurchaseReturn
├── Controllers
│   └── PurchaseReturnController.php
│
├── Models
│   ├── PurchaseReturn.php
│   └── PurchaseReturnItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── PurchaseReturnRepositoryInterface.php
│   └── PurchaseReturnRepository.php
│
├── Services
│   └── PurchaseReturnService.php
│
├── Requests
│   ├── StorePurchaseReturnRequest.php
│   └── UpdatePurchaseReturnRequest.php
│
├── Resources
│   ├── PurchaseReturnResource.php
│   └── PurchaseReturnItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── PurchaseReturnServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('purchase_returns', function (Blueprint $table) {

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
