<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;
use App\Modules\Accounting\Models\ChartOfAccount;

class TaxAccountingMapping extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tax_accounting_mappings';

    protected $fillable = [
        'tax_component_id',
        'account_id',
        'direction',
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

    public function taxComponent(): BelongsTo
    {
        return $this->belongsTo(
            TaxComponent::class,
            'tax_component_id'
        );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            ChartOfAccount::class,
            'account_id'
        );
    }
}