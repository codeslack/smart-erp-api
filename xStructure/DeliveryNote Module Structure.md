# DeliveryNote Module

1. bash make-module-repo.sh DeliveryNote
2. make table name=delivery_notes
3. make table name=delivery_note_items
4. code App\Modules\DeliveryNote\Repositories\DeliveryNoteRepository.php
5. code App\Modules\DeliveryNote\Repositories\Contracts\DeliveryNoteRepositoryInterface.php
6. code App\Modules\DeliveryNote\Services\DeliveryNoteService.php
7. make db-up
8. code bootstrap\providers.php
   |-App\Modules\DeliveryNote\Providers\DeliveryNoteServiceProvider::class
9. git commit -m "✅ DeliveryNote Module Complete (Version 1)"

# Phase 1: DeliveryNote Module Structure

```bash
php artisan make:migration create_delivery_notes_table
php artisan make:migration create_delivery_note_items_table

DeliveryNote
├── Controllers
│   └── DeliveryNoteController.php
|
├── Enums
│   └── DeliveryNoteStatus.php
│
├── Models
│   ├── DeliveryNote.php
│   └── DeliveryNoteItem.php
│
├── Repositories
│   ├── Contracts
│   │   └── DeliveryNoteRepositoryInterface.php
│   └── DeliveryNoteRepository.php
│
├── Services
│   └── DeliveryNoteService.php
│
├── Requests
│   ├── StoreDeliveryNoteRequest.php
│   └── UpdateDeliveryNoteRequest.php
│
├── Resources
│   ├── DeliveryNoteResource.php
│   └── DeliveryNoteItemResource.php
│
├── Routes
│   └── api.php
│
└── Providers
    └── DeliveryNoteServiceProvider.php
```
