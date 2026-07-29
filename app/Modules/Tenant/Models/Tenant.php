<?php

namespace App\Modules\Tenant\Models;

use App\Modules\Settings\Models\Setting;
use App\Modules\Tenant\Enums\BusinessTypeEnum;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'slug',
        'domain',
        'business_type',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'business_type' => BusinessTypeEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): HasMany
    {
        return $this->hasMany(
            User::class
        );
    }

    public function settings(): HasMany
    {
        return $this->hasMany(
            Setting::class
        );
    }
}
