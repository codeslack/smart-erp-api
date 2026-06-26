<?php

namespace App\Modules\User\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Core\Tenant\TenantAuthenticatable;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends TenantAuthenticatable
{
    use HasFactory;
    use HasApiTokens;
    use HasRoles;
    use Notifiable;

    protected string $guard_name = 'sanctum';

    public function getDefaultGuardName(): string
    {
        return $this->guard_name;
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

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

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }
}
