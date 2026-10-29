<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'supplier_id',
        'return_no',
        'return_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'rounding_amount',
        'grand_total',
        'refund_amount',
        'credited_amount',
        'refund_type',
        'refund_account_id',
        'return_reason',
        'status',
        'journal_entry_id',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'return_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'rounding_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'refund_amount' => 'decimal:4',
            'credited_amount' => 'decimal:4',
            'refund_type' => PurchaseReturnRefundType::class,
            'status' => PurchaseReturnStatus::class,
            'approved_at' => 'datetime',
        ]);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id')->withDefault();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PurchaseReturnItemBatch::class, 'purchase_return_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(PurchaseReturnItemSerial::class, 'purchase_return_id');
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'refund_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseReturnStatus::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === PurchaseReturnStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseReturnStatus::CANCELLED;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where(function ($q) use ($value) {
            if (is_numeric($value)) {
                $q->where('id', (int) $value);
            }
            $q->orWhere('uuid', (string) $value);
        })->first() ?? abort(404, 'Purchase return not found.');
    }
}
