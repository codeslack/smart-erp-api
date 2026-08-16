<?php

namespace App\Modules\OpeningStock\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;

class OpeningStock extends TenantModel
{
    use SoftDeletes;

    protected $table = 'opening_stocks';

    protected $fillable = [
        'document_no',
        'warehouse_id',
        'opening_date',
        'status',
        'total_quantity',
        'total_amount',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'opening_date'   => 'date',
                'total_quantity' => 'decimal:4',
                'total_amount'   => 'decimal:4',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
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