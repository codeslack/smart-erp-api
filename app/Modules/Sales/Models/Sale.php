<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\TenantModel;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\Customer\Models\Customer;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Receipt\Models\ReceiptAllocation;
use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'tenant_id',
        'sale_no',
        'sale_date',
        'due_date',
        'customer_reference_number',
        'customer_id',
        'warehouse_id',
        'sales_order_id',
        'delivery_note_id',
        'sales_quotation_id',
        'payment_term_id',
        'status',
        'currency_code',
        'exchange_rate',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'discount',
        'charges_subtotal',
        'charges_tax_amount',
        'tax_amount',
        'tax',
        'rounding_amount',
        'grand_total',
        'paid_amount',
        'due_amount',
        'journal_entry_id',
        'notes',
        'terms_and_conditions',
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
            'sale_date' => 'date',
            'due_date' => 'date',
            'status' => SaleStatusEnum::class,
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'discount' => 'decimal:4',
            'charges_subtotal' => 'decimal:4',
            'charges_tax_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'tax' => 'decimal:4',
            'rounding_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'due_amount' => 'decimal:4',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class, 'document_id')
            ->where(function ($query) {
                $query->where('document_type', self::class)
                    ->orWhere('document_type', 'sale')
                    ->orWhere('document_type', 'sale_invoice');
            });
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(AdvanceAllocation::class, 'target_id')
            ->where('target_type', self::class);
    }

    public function isDraft(): bool
    {
        return $this->status === SaleStatusEnum::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === SaleStatusEnum::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatusEnum::CANCELLED;
    }

    public function getSubtotalAmountAttribute(): float
    {
        return (float) $this->subtotal;
    }

    public function getGrandTotalAmountAttribute(): float
    {
        return (float) $this->grand_total;
    }

    public function getTaxTotalAmountAttribute(): float
    {
        return (float) $this->tax_amount;
    }
}
