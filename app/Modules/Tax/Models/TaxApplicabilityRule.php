<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

class TaxApplicabilityRule extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tax_applicability_rules';

    protected $fillable = [
        'tax_group_id',
        'source_country_code',
        'source_state_code',
        'destination_country_code',
        'destination_state_code',
        'place_of_supply_state_code',
        'source_registration_required',
        'destination_registration_required',
        'priority',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'source_registration_required' => 'boolean',
                'destination_registration_required' => 'boolean',
                'priority' => 'integer',
                'is_active' => 'boolean',
                'effective_from' => 'date',
                'effective_to' => 'date',
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
}