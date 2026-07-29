<?php

namespace App\Modules\Settings\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
    extends BaseRepositoryInterface
{
    public function get(
        string $group,
        string $key,
        mixed $default = null
    ): mixed;

    public function set(
        string $group,
        string $key,
        mixed $value
    ): void;

    public function getGroup(
        string $group
    ): Collection;

    public function updateGroup(
        string $group,
        array $settings
    ): void;
}