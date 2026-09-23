<?php

namespace App\Modules\Customer\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\PaymentTerm\Models\PaymentTerm;

class Customer extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [

        'name',
        'code',

        'contact_person',

        'phone',
        'email',

        'address',

        'tax_number',

        'payment_term_id',

        'credit_days',
        'credit_limit',
        'credit_control',

        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'credit_days' => 'integer',
                'credit_limit' => 'decimal:4',
                'is_active' => 'boolean',
            ]
        );
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(
            PaymentTerm::class
        );
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
