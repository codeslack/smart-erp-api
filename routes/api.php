<?php

use Illuminate\Support\Facades\Route;

Route::get(
    '/modules',
    fn () => [

        'modules' =>
            \App\Core\Modules\ModuleRegistry::modules(),

        'permissions' =>
            \App\Core\Modules\ModuleRegistry::permissions(),

        'setup_services' =>
            \App\Core\Modules\ModuleRegistry::setupServices(),
    ]
);