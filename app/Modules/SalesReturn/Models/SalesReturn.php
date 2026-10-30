<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales_returns';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'return_no',
        'sale_id',
        'customer_id',
        'warehouse_id',
        'return_date',
        'currency_code',
        'exchange_rate',
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
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'return_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'rounding_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'refund_amount' => 'decimal:4',
            'credited_amount' => 'decimal:4',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refund_type' => SalesReturnRefundType::class,
            'status' => SalesReturnStatus::class,
        ]);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'refund_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_return_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === SalesReturnStatus::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === SalesReturnStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === SalesReturnStatus::CANCELLED;
    }
}
