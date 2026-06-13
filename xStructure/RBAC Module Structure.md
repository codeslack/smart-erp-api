
# RBAC Module Structure

Modules/
└── Rbac/
    ├── Contracts/
    │   └── RoleRepositoryInterface.php
    │
    ├── Repositories/
    │   └── RoleRepository.php
    │
    ├── Services/
    │   └── RoleService.php
    │
    ├── Controllers/
    │   └── RoleController.php
    │
    ├── Requests/
    │   ├── StoreRoleRequest.php
    │   └── UpdateRoleRequest.php
    │
    ├── Resources/
    │   └── RoleResource.php
    │
    ├── Models/
    │   └── Role.php
    │
    ├── Providers/
    │   └── RbacServiceProvider.php
    │
    └── Routes/
        └── api.php

```<?php

public function store(
    StoreRoleRequest $request
)
{
    $role = $this->roleService->create(
        $request->validated()
    );

    return new RoleResource($role);
}


RoleController.php
PermissionController.php
StoreRoleRequest.php
UpdateRoleRequest.php
RoleResource.php
PermissionResource.php
routes.php


Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->prefix('rbac')->group(function () {

    Route::apiResource('roles', RoleController::class);

    Route::get(
        'permissions',
        [PermissionController::class, 'index']
    );
});

public function create(array $data): Role
{
    return $this->repository->create([
        'tenant_id' => tenant()->id,
        'name' => $data['name'],
        'guard_name' => 'sanctum',
    ]);
}