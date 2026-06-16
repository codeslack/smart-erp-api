<?php

namespace App\Modules\Unit\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UnitFactory::new();
    }
}