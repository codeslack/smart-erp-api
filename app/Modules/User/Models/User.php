<?php

namespace App\Modules\User\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Core\Tenant\TenantAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends TenantAuthenticatable
{
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
}
