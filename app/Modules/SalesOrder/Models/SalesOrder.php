<?php

namespace App\Modules\SalesOrder\Models;

use App\Core\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'order_date',
        'expected_delivery_date',
        'customer_id',
        'warehouse_id',
        'payment_term_id',
        'sales_quotation_id',
        'status',
        'currency_code',
        'exchange_rate',
        'subtotal_amount',
        'discount_amount',
        'additional_charge_amount',
        'tax_total_amount',
        'rounding_adjustment',
        'grand_total_amount',
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
        return array_merge(
            parent::casts(),
            [
                'order_date' => 'date',
                'expected_delivery_date' => 'date',
                'status' => SalesOrderStatusEnum::class,
                'exchange_rate' => 'decimal:6',
                'subtotal_amount' => 'decimal:4',
                'discount_amount' => 'decimal:4',
                'additional_charge_amount' => 'decimal:4',
                'tax_total_amount' => 'decimal:4',
                'rounding_adjustment' => 'decimal:4',
                'grand_total_amount' => 'decimal:4',
                'confirmed_at' => 'datetime',
                'cancelled_at' => 'datetime',
            ]
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function salesQuotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class, 'sales_quotation_id');
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
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function getSoNoAttribute(): ?string
    {
        return $this->order_number;
    }

    public function setSoNoAttribute(?string $value): void
    {
        $this->attributes['order_number'] = $value;
    }

    public function getSubtotalAttribute(): mixed
    {
        return $this->subtotal_amount;
    }

    public function getGrandTotalAttribute(): mixed
    {
        return $this->grand_total_amount;
    }
}
