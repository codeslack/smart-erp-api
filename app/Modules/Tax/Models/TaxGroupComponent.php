<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class TaxGroupComponent extends TenantModel
{
    protected $table = 'tax_group_components';

    protected $fillable = [
        'tax_group_id',
        'tax_component_id',
        'rate',
        'calculation_method',
        'is_active',
        'effective_from',
        'effective_to',
        'sort_order',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'rate' => 'decimal:6',
                'is_active' => 'boolean',
                'effective_from' => 'date',
                'effective_to' => 'date',
                'sort_order' => 'integer',
            ]
        );
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(
            TaxGroup::class,
            'tax_group_id'
        );
    }

    public function taxComponent(): BelongsTo
    {
        return $this->belongsTo(
            TaxComponent::class,
            'tax_component_id'
        );
    }
}