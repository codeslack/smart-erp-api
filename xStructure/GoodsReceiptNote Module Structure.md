# GoodsReceiptNote Module

1. bash make-module-repo.sh GoodsReceiptNote
2. make table name=goods_receipt_notes
3. make table name=goods_receipt_note_items
4. code App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteRepository.php
5. code App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface.php
6. code App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\GoodsReceiptNote\Providers\GoodsReceiptNoteServiceProvider::class
9. git commit -m "✅ GoodsReceiptNote Module Complete (Version 1)"

# Phase 1: GoodsReceiptNote Module Structure

```bash
php artisan make:migration create_goods_receipt_notes_table
php artisan make:migration create_goods_receipt_note_items_table

GoodsReceiptNote
├── Controllers
│   └── GoodsReceiptNoteController.php
│
├── Models
│   ├── GoodsReceiptNote.php
│   └── GoodsReceiptNoteItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── GoodsReceiptNoteRepositoryInterface.php
│   └── GoodsReceiptNoteRepository.php
│
├── Services
│   └── GoodsReceiptNoteService.php
│
├── Requests
│   ├── StoreGoodsReceiptNoteRequest.php
│   └── UpdateGoodsReceiptNoteRequest.php
│
├── Resources
│   ├── GoodsReceiptNoteResource.php
│   └── GoodsReceiptNoteItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── GoodsReceiptNoteServiceProvider.php
```
