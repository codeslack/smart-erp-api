<?php

namespace App\Modules\Purchase\Models;

use App\Core\Models\TenantModel;
use App\Core\Traits\HasUuidPrimaryKey;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Tax\Models\Tax;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseCharge extends TenantModel
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'purchase_charges';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'purchase_id',
        'charge_type',
        'name',
        'calculation_method',
        'calculation_value',
        'amount',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'treatment',
        'allocation_method',
        'affects_inventory_cost',
        'chart_of_account_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'calculation_value' => 'decimal:4',
            'amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'affects_inventory_cost' => 'boolean',
            'sort_order' => 'integer',
        ]);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function isLandedCost(): bool
    {
        return $this->treatment === 'landed_cost' || $this->affects_inventory_cost;
    }
}
