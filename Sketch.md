
```
erp-api
├─ app
│  ├─ Core
│  │  ├─ Contracts
│  │  │  └─ BaseRepositoryInterface.php
│  │  ├─ Repositories
│  │  │  └─ BaseRepository.php
│  │  └─ Tenant
│  │     ├─ helpers.php
│  │     ├─ Middleware
│  │     │  ├─ SetPermissionTenant.php
│  │     │  └─ TenantMiddleware.php
│  │     ├─ Models
│  │     │  └─ TenantModel.php
│  │     ├─ TenantAuthenticatable.php
│  │     ├─ TenantManager.php
│  │     ├─ TenantMiddleware.php
│  │     ├─ TenantModel.php
│  │     ├─ TenantResolver.php
│  │     ├─ TenantScope.php
│  │     └─ TenantTeamResolver.php
│  ├─ Http
│  │  └─ Controllers
│  │     └─ Controller.php
│  ├─ Models
│  │  └─ User.php
│  ├─ Modules
│  │  ├─ Customer
│  │  │  └─ Models
│  │  │     └─ Customer.php
│  │  ├─ Rbac
│  │  │  ├─ Contracts
│  │  │  │  └─ RoleRepositoryInterface.php
│  │  │  ├─ Controllers
│  │  │  │  ├─ PermissionController.php
│  │  │  │  ├─ RoleController.php
│  │  │  │  ├─ RolePermissionController.php
│  │  │  │  └─ UserRoleController.php
│  │  │  ├─ Models
│  │  │  │  ├─ Permission.php
│  │  │  │  └─ Role.php
│  │  │  ├─ Providers
│  │  │  │  └─ RbacServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  └─ RoleRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ AssignPermissionsRequest.php
│  │  │  │  ├─ AssignRolesRequest.php
│  │  │  │  ├─ StoreRoleRequest.php
│  │  │  │  └─ UpdateRoleRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ PermissionResource.php
│  │  │  │  ├─ RoleResource.php
│  │  │  │  └─ UserPermissionResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  ├─ Seeders
│  │  │  │  ├─ PermissionSeeder.php
│  │  │  │  └─ RoleSeeder.php
│  │  │  └─ Services
│  │  │     └─ RoleService.php
│  │  ├─ Tenant
│  │  │  ├─ Controllers
│  │  │  │  └─ TenantController.php
│  │  │  ├─ Models
│  │  │  │  └─ Tenant.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ TenantRepositoryInterface.php
│  │  │  │  └─ TenantRepository.php
│  │  │  ├─ Requests
│  │  │  │  └─ StoreTenantRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ TenantResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ TenantService.php
│  │  ├─ User
│  │  │  ├─ Controllers
│  │  │  │  ├─ AuthController.php
│  │  │  │  └─ UserController.php
│  │  │  ├─ Models
│  │  │  │  └─ User.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ UserRepositoryInterface.php
│  │  │  │  └─ UserRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ LoginRequest.php
│  │  │  │  └─ StoreUserRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ UserResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     ├─ AuthService.php
│  │  │     └─ UserService.php
│  ├─ Providers
│  │  └─ AppServiceProvider.php
│  └─ Shared
├─ artisan
├─ bootstrap
│  ├─ app.php
│  ├─ cache
│  │  ├─ packages.php
│  │  └─ services.php
│  └─ providers.php
├─ composer.json
├─ composer.lock
├─ config
│  ├─ app.php
│  ├─ auth.php
│  ├─ cache.php
│  ├─ database.php
│  ├─ erp_permissions.php
│  ├─ filesystems.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ permission.php
│  ├─ queue.php
│  ├─ sanctum.php
│  ├─ services.php
│  └─ session.php
├─ database
│  ├─ factories
│  │  └─ UserFactory.php
│  ├─ migrations
│  │  ├─ 0000_01_01_000000_create_tenants_table.php
│  │  ├─ 0001_01_01_000000_create_users_table.php
│  │  ├─ 0001_01_01_000001_create_cache_table.php
│  │  ├─ 0001_01_01_000002_create_jobs_table.php
│  │  ├─ 2026_06_11_184030_create_personal_access_tokens_table.php
│  │  ├─ 2026_06_11_201424_create_customers_table.php
│  │  ├─ 2026_06_12_072137_create_permission_tables.php
│  └─ seeders
│     ├─ AdminUserSeeder.php
│     ├─ DatabaseSeeder.php
│     └─ TenantSeeder.php
├─ Makefile
├─ package.json
├─ phpunit.xml
├─ public
│  ├─ .htaccess
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ README.md
├─ resources
│  ├─ css
│  │  └─ app.css
│  ├─ js
│  │  └─ app.js
│  └─ views
│     └─ welcome.blade.php
├─ routes
│  ├─ api.php
│  ├─ console.php
│  └─ web.php
├─ storage
│  ├─ app
│  │  ├─ private
│  │  └─ public
│  ├─ framework
│  │  ├─ cache
│  │  │  └─ data
│  │  ├─ sessions
│  │  ├─ testing
│  │  └─ views
│  └─ logs
├─ tests
│  ├─ Feature
│  │  └─ ExampleTest.php
│  ├─ TestCase.php
│  └─ Unit
│     └─ ExampleTest.php
├─ todo-list.md
├─ vite.config.js

```