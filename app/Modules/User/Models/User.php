<?php

namespace App\Modules\User\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantAuthenticatable;

class User extends TenantAuthenticatable
{
    use HasApiTokens;
    use Notifiable;
    use HasRoles;

    protected $table = 'users';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [

            'uuid' => 'string',

            'email_verified_at' => 'datetime',

            'last_login_at' => 'datetime',

            'is_active' => 'boolean',

            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Tenant\Models\Tenant::class
        );
    }   

    public function journalEntries(): HasMany
    {
        return $this->hasMany(
            JournalEntry::class,
            'created_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->hasRole(
            'Administrator'
        );
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }    
}