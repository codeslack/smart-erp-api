<?php

use Illuminate\Support\Facades\Route;

require base_path(
    'app/Modules/Tenant/Routes/api.php'
);

Route::middleware('tenant')
    ->get('/current-tenant', function () {

        return [
            'id' => tenant()->id,
            'name' => tenant()->name,
            'slug' => tenant()->slug,
        ];
    });