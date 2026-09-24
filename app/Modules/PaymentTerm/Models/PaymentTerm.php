<?php

namespace App\Modules\PaymentTerm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Core\Models\TenantModel;

use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;

class PaymentTerm extends TenantModel
{
    use SoftDeletes;

    protected $table = 'payment_terms';

    protected $fillable = [

        'code',

        'name',

        'due_days',

        'discount_days',

        'discount_percent',

        'grace_days',

        'description',

        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'due_days' => 'integer',

                'discount_days' => 'integer',

                'discount_percent' => 'decimal:4',

                'grace_days' => 'integer',

                'is_active' => 'boolean',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customers(): HasMany
    {
        return $this->hasMany(
            Customer::class
        );
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(
            Supplier::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }
}