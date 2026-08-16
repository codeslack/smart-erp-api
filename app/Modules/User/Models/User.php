<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;

use App\Core\Models\TenantAuthenticatable;

use App\Modules\Accounting\Models\JournalEntry;

class User extends TenantAuthenticatable
{
    use HasApiTokens;
    use Notifiable;
    use HasRoles;
    use SoftDeletes;

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


    /*
    |--------------------------------------------------------------------------
    | Media / Avatar Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Get the full URL for the avatar.
     */
    // public function getAvatarUrlAttribute(): string
    // {
    //     if (!$this->avatar_path) {
    //         return "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&color=7F9CF5&background=EBF4FF";
    //     }

    //     // Returns a route to the FileController for private file streaming
    //     return route('api.v1.files.show', ['file' => $this->avatar_path]);
    // }    
}