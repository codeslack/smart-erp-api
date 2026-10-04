<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

use App\Modules\Tax\Models\TaxGroupComponent;
use App\Modules\Tax\Models\TaxAccountingMapping;

class TaxComponent extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tax_components';

    protected $fillable = [
        'code',
        'name',
        'component_type',
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

    public function taxGroupComponents(): HasMany
    {
        return $this->hasMany(
            TaxGroupComponent::class,
            'tax_component_id'
        );
    }

    public function accountingMappings(): HasMany
    {
        return $this->hasMany(
            TaxAccountingMapping::class,
            'tax_component_id'
        );
    }
}