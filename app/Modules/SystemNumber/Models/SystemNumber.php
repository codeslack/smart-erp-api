<?php

namespace App\Modules\SystemNumber\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\BaseModel;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

class SystemNumber extends BaseModel
{
    protected $table = 'system_numbers';

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