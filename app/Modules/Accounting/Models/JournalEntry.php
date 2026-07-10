<?php

namespace App\Modules\Accounting\Models;

use App\Modules\User\Models\User;
use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'voucher_no',
        'voucher_type',

        'reference_type',
        'reference_id',

        'entry_date',

        'description',

        'status',

        'created_by',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [

        'entry_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(
            AccountLedger::class
        );
    }    
}