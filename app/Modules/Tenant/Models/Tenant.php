<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Core\Models\BaseModel;

use App\Modules\User\Models\User;
use App\Modules\Settings\Models\Setting;
use App\Modules\Tenant\Enums\BusinessTypeEnum;

class Tenant extends BaseModel
{
    use SoftDeletes, HasUuids;

    protected $table = 'tenants';

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
