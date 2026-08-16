<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Area\Models\Area;

class Warehouse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'warehouses';

    protected $fillable = [

        'tenant_id',

        'code',

        'name',

        'area_id',

        'contact_person',

        'phone',

        'email',

        'address',

        'is_default',

        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'area_id' => 'integer',

                'is_default' => 'boolean',

                'is_active' => 'boolean',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function area(): BelongsTo
    {
        return $this->belongsTo(
            Area::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}