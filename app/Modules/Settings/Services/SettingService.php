<?php

namespace App\Modules\Settings\Services;

use Illuminate\Support\Collection;
use App\Modules\Settings\Repositories\Contracts\SettingRepositoryInterface;

class SettingService
{
    public function __construct(
        protected SettingRepositoryInterface $settings
    ) {
    }

    public function get(
        string $group,
        string $key,
        mixed $default = null
    ): mixed {

        return $this->settings->get(
            $group,
            $key,
            $default
        );
    }

    public function set(
        string $group,
        string $key,
        mixed $value
    ): void {

        $this->settings->set(
            $group,
            $key,
            $value
        );
    }

    public function getGroup(
        string $group
    ): Collection {

        return $this->settings->getGroup(
            $group
        );
    }

    public function updateGroup(
        string $group,
        array $settings
    ): void {

        $this->settings->updateGroup(
            $group,
            $settings
        );
    }
}