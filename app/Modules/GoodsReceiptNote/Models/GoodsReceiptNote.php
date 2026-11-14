<?php

namespace App\Modules\GoodsReceiptNote\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;

class GoodsReceiptNote extends TenantModel
{
    use SoftDeletes;

    protected $table = 'goods_receipt_notes';

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'grn_number',
        'grn_date',
        'status',
        'delivery_challan_number',
        'delivery_challan_date',
        'vehicle_number',
        'transporter_name',
        'remarks',
        'total_received_valuation',
        'inventory_currency_code',
        'inventory_exchange_rate',
        'journal_entry_id',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'grn_date' => 'date',
                'delivery_challan_date' => 'date',
                'status' => GoodsReceiptNoteStatusEnum::class,
                'total_received_valuation' => 'decimal:4',
                'inventory_exchange_rate' => 'decimal:8',
                'confirmed_at' => 'datetime',
                'cancelled_at' => 'datetime',
            ]
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class,
            'purchase_order_id'
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class,
            'supplier_id'
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'warehouse_id'
        );
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class,
            'journal_entry_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptNoteItem::class,
            'goods_receipt_note_id'
        );
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by'
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function isDraft(): bool
    {
        return $this->status === GoodsReceiptNoteStatusEnum::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === GoodsReceiptNoteStatusEnum::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === GoodsReceiptNoteStatusEnum::CANCELLED;
    }
}