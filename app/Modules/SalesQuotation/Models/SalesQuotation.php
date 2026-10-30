<?php

namespace App\Modules\SalesQuotation\Models;

use App\Core\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuotation extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales_quotations';

    protected $fillable = [
        'customer_id',
        'warehouse_id',
        'quotation_no',
        'quotation_date',
        'expiry_date',
        'currency_code',
        'exchange_rate',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'rounding_amount',
        'grand_total',
        'status',
        'notes',
        'terms_conditions',
        'converted_sales_order_id',
        'converted_sale_id',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'expiry_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'shipping_amount' => 'decimal:4',
            'rounding_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesQuotationItem::class, 'sales_quotation_id');
    }

    public function convertedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === SalesQuotationStatusEnum::DRAFT->value;
    }

    public function isSent(): bool
    {
        return $this->status === SalesQuotationStatusEnum::SENT->value;
    }

    public function isAccepted(): bool
    {
        return $this->status === SalesQuotationStatusEnum::ACCEPTED->value;
    }

    public function isConverted(): bool
    {
        return in_array($this->status, [
            SalesQuotationStatusEnum::CONVERTED_TO_ORDER->value,
            SalesQuotationStatusEnum::CONVERTED_TO_SALE->value,
        ], true);
    }
}
