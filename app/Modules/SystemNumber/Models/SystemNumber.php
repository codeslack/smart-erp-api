<?php

namespace App\Modules\SystemNumber\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

class SystemNumber extends Model
{
    protected $table = 'system_numbers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
            'type'           => SystemNumberTypeEnum::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class
        );
    }
}