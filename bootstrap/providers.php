<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    App\Modules\Rbac\Providers\RbacServiceProvider::class,
    App\Modules\Unit\Providers\UnitServiceProvider::class,
];
