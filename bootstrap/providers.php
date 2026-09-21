<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    App\Core\Modules\ModuleServiceProvider::class,

    App\Modules\Rbac\Providers\RbacServiceProvider::class,

    App\Modules\Supplier\Providers\SupplierServiceProvider::class,

];
