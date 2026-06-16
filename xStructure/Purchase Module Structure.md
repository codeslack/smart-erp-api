# Purchase Module

1. bash make-module.sh Purchase
2. make table name=purchases
3. code App\Modules\Purchase\Repositories\PurchaseRepository.php
4. code App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface.php
5. code App\Modules\Purchase\Services\PurchaseService.php
6. make db-up
7. code bootstrap\providers.php
   |-App\Modules\Purchase\Providers\PurchaseServiceProvider::class
8. git commit -m "✅ Purchase Module Complete (Version 1)"

# Phase 1: Purchase Module Structure

```bash
Purchase
├── Controllers
│   └── PurchaseController.php
│
├── Models
│   ├── Purchase.php
│   └── PurchaseItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── PurchaseRepositoryInterface.php
│   └── PurchaseRepository.php
│
├── Services
│   └── PurchaseService.php
│
├── Requests
│   ├── StorePurchaseRequest.php
│   └── UpdatePurchaseRequest.php
│
├── Resources
│   ├── PurchaseResource.php
│   └── PurchaseItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── PurchaseServiceProvider.php
```

# Step 1: Create Migrations

```bash
Schema::create('purchases', function (Blueprint $table) {

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
