<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

class Tax extends TenantModel
{
    use SoftDeletes;

    protected $table = 'taxes';

    protected $fillable = [
        'code',
        'name',
        'tax_type',
        'description',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'is_active' => 'boolean',
                'effective_from' => 'date',
                'effective_to' => 'date',
            ]
        );
    }

    public function groups(): HasMany
    {
        return $this->hasMany(
            TaxGroup::class,
            'tax_id'
        );
    }
}