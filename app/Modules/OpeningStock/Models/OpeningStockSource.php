<?php

namespace App\Modules\OpeningStock\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;

class OpeningStockSource extends TenantModel
{
    use SoftDeletes;

    protected $table = 'opening_stock_sources';

    protected $fillable = [
        'opening_stock_id',
        'supplier_id',
        'bill_no',
        'bill_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'bill_date' => 'date',
            ]
        );
    }

    public function openingStock(): BelongsTo
    {
        return $this->belongsTo(
            OpeningStock::class
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            OpeningStockItem::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}