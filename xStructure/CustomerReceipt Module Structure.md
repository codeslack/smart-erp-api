# CustomerReceipt Module

1. bash make-module-repo.sh CustomerReceipt
2. make table name=customer_receipts
3. make table name=customer_receipt_allocations
4. code App\Modules\CustomerReceipt\Repositories\CustomerReceiptRepository.php
5. code App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface.php
6. code App\Modules\CustomerReceipt\Services\CustomerReceiptService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\CustomerReceipt\Providers\CustomerReceiptServiceProvider::class
9. git commit -m "✅ CustomerReceipt Module Complete (Version 1)"

# Phase 1: CustomerReceipt Module Structure

```bash
php artisan make:migration create_customer_receipts_table
php artisan make:migration create_customer_receipt_allocations_table

CustomerReceipt
├── Controllers
│   └── CustomerReceiptController.php
|
├── Enums
│   └── CustomerReceiptStatus.php
│
├── Models
│   ├── CustomerReceipt.php
│   └── CustomerReceiptItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── CustomerReceiptRepositoryInterface.php
│   └── CustomerReceiptRepository.php
│
├── Services
│   └── CustomerReceiptService.php
│
├── Requests
│   ├── StoreCustomerReceiptRequest.php
│   └── UpdateCustomerReceiptRequest.php
│
├── Resources
│   ├── CustomerReceiptResource.php
│   └── CustomerReceiptItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── CustomerReceiptServiceProvider.php
```
