<?php

namespace App\Modules\PurchaseOrder\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\PaymentTerm\Models\PaymentTerm;

class PurchaseOrder extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_number',
        'order_date',
        'expected_delivery_date',
        'supplier_id',
        'warehouse_id',
        'payment_term_id',
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

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(
            PaymentTerm::class,
            'payment_term_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseOrderItem::class,
            'purchase_order_id'
        );
    }
}