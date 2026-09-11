<?php

namespace App\Modules\Area\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Warehouse\Models\Warehouse;


class Area extends TenantModel
{
    use SoftDeletes;

    protected $table = 'areas';

    protected $fillable = [

        'tenant_id',

        'code',

        'name',

        'description',

        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'is_active' => 'boolean',
            ]
        );
    }

    public function setNameAttribute(string $name)
    {
        $this->attributes['name'] = strtolower($name);
    }

    public function getNameAttribute(string $name)
    {
        return ucwords($name);
    }

    public function setDescriptionAttribute(string $description)
    {
        $this->attributes['description'] = strtolower($description);
    }

    public function getDescriptionAttribute(string $description)
    {
        return ucwords($description);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class
        );
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(
            Warehouse::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }
}