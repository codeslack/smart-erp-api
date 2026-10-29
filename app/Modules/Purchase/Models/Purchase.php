<?php

namespace App\Modules\Purchase\Models;

use App\Core\Models\TenantModel;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchases';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'warehouse_id',
        'purchase_order_id',
        'goods_receipt_note_id',
        'payment_term_id',
        'purchase_no',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'purchase_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'tax_calculation_mode',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'charges_subtotal',
        'charges_tax_amount',
        'tax_amount',
        'rounding_amount',
        'grand_total',
        'paid_amount',
        'due_amount',
        'journal_entry_id',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'supplier_invoice_date' => 'date',
            'purchase_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'charges_subtotal' => 'decimal:4',
            'charges_tax_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'rounding_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'due_amount' => 'decimal:4',
            'status' => PurchaseStatusEnum::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ]);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(PurchaseCharge::class, 'purchase_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'document_id')
            ->where(function ($query) {
                $query->where('document_type', self::class)
                    ->orWhere('document_type', 'purchase')
                    ->orWhere('document_type', 'purchase_invoice');
            });
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(AdvanceAllocation::class, 'target_id')
            ->where('target_type', self::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatusEnum::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === PurchaseStatusEnum::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseStatusEnum::CANCELLED;
    }

    public function isDirect(): bool
    {
        return $this->goods_receipt_note_id === null;
    }

    public function getReturnedAmountAttribute(): float
    {
        return (float) $this->purchaseReturns()
            ->where('status', PurchaseReturnStatus::CONFIRMED)
            ->sum('grand_total');
    }

    public function getNetPurchaseAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - (float) $this->returned_amount);
    }
}
