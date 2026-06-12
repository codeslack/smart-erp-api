<?php

use Illuminate\Support\Facades\Route;

require base_path(
    'app/Modules/Tenant/Routes/api.php'
);

require_once base_path(
    'app/Modules/User/Routes/api.php'
);

Route::middleware('tenant')
    ->get('/customer-test', function () {

        return \App\Modules\Customer\Models\Customer::all();
    });

Route::middleware('tenant')
    ->post('/customer-test', function () {

        return \App\Modules\Customer\Models\Customer::create([
            'name' => 'Customer A',
        ]);
    });

Route::middleware('tenant')
    ->get('/current-tenant', function () {

        return [
            'id' => tenant()->id,
            'name' => tenant()->name,
            'slug' => tenant()->slug,
        ];
    });

Route::get('/rbac-test', function () {

    return [
        'tenant' => tenant()?->id,
        'team_id' => app(
            \Spatie\Permission\PermissionRegistrar::class
        )->getPermissionsTeamId(),
    ];

})->middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
]);    