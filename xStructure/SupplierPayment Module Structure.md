# SupplierPayment Module

1. bash make-module-repo.sh SupplierPayment
2. make table name=supplier_receipts
3. make table name=supplier_receipt_allocations
4. code App\Modules\SupplierPayment\Repositories\SupplierPaymentRepository.php
5. code App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface.php
6. code App\Modules\SupplierPayment\Services\SupplierPaymentService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\SupplierPayment\Providers\SupplierPaymentServiceProvider::class
9. git commit -m "✅ SupplierPayment Module Complete (Version 1)"

# Phase 1: SupplierPayment Module Structure

```bash
php artisan make:migration create_supplier_receipts_table
php artisan make:migration create_supplier_receipt_allocations_table

SupplierPayment
├── Controllers
│   └── SupplierPaymentController.php
|
├── Enums
│   └── SupplierPaymentStatus.php
│
├── Models
│   ├── SupplierPayment.php
│   └── SupplierPaymentItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── SupplierPaymentRepositoryInterface.php
│   └── SupplierPaymentRepository.php
│
├── Services
│   └── SupplierPaymentService.php
│
├── Requests
│   ├── StoreSupplierPaymentRequest.php
│   └── UpdateSupplierPaymentRequest.php
│
├── Resources
│   ├── SupplierPaymentResource.php
│   └── SupplierPaymentItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── SupplierPaymentServiceProvider.php
```
