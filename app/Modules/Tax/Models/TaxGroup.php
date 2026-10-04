<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

class TaxGroup extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tax_groups';

    protected $fillable = [
        'tax_id',
        'code',
        'name',
        'usage',
        'direction',
        'treatment',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ]);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(
            Tax::class,
            'tax_id'
        );
    }

    public function components(): HasMany
    {
        return $this->hasMany(
            TaxGroupComponent::class,
            'tax_group_id'
        );
    }

    public function applicabilityRules(): HasMany
    {
        return $this->hasMany(
            TaxApplicabilityRule::class,
            'tax_group_id'
        );
    }
}