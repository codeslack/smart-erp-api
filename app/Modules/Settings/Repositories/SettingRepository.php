<?php

namespace App\Modules\Settings\Repositories;

use Illuminate\Support\Collection;

use App\Modules\Settings\Models\Setting;

use App\Core\Repositories\BaseRepository;
use App\Modules\Settings\Repositories\Contracts\SettingRepositoryInterface;

/**
 * @extends BaseRepository<Setting>
 */
class SettingRepository
    extends BaseRepository
    implements SettingRepositoryInterface
{
    public function __construct(
        Setting $setting
    ) {
        $this->model = $setting;
    }

    public function get(
        string $group,
        string $key,
        mixed $default = null
    ): mixed {

        return $this->model
            ->newQuery()
            ->where('group', $group)
            ->where('key', $key)
            ->value('value')
            ?? $default;
    }

    public function set(
        string $group,
        string $key,
        mixed $value
    ): void {

        $this->model
            ->newQuery()
            ->updateOrCreate(
                [
                    'group' => $group,
                    'key'   => $key,
                ],
                [
                    'value' => $value,
                ]
            );
    }

    public function getGroup(
        string $group
    ): Collection {

        return $this->model
            ->newQuery()
            ->where('group', $group)
            ->get()
            ->pluck(
                'value',
                'key'
            );
    }

    public function updateGroup(
        string $group,
        array $settings
    ): void {

        foreach (
            $settings as $key => $value
        ) {

            $this->set(
                $group,
                $key,
                $value
            );
        }
    }
}