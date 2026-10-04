<?php

namespace App\Modules\Tax\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

class TaxRegistration extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tax_registrations';

    protected $fillable = [
        'registrable_type',
        'registrable_id',
        'registration_type',
        'registration_number',
        'country_code',
        'state_code',
        'is_primary',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'registrable_id' => 'integer',
                'is_primary' => 'boolean',
                'is_active' => 'boolean',
                'effective_from' => 'date',
                'effective_to' => 'date',
            ]
        );
    }

    public function registrable(): MorphTo
    {
        return $this->morphTo();
    }
}