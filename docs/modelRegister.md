# ERP Module Registration Architecture

**File:** `docs/modelRegister.md`

## 1. Overview

The ERP uses a centralized, configuration-driven module registration architecture.

Each module implements `ModuleInterface` and declares:

- Module name
- Module permissions
- Service providers
- Tenant setup services

The central `ModuleRegistry` discovers these modules from `config/modules.php`.

This avoids manually registering every module's service provider in Laravel's bootstrap configuration.

## 2. Registration Flow

The registration process follows this sequence:

```text
bootstrap/providers.php
        |
        v
ModuleServiceProvider
        |
        v
ModuleRegistry
        |
        v
config/modules.php
        |
        v
Module::serviceProviders()
        |
        v
Laravel Service Container
        |
        v
Module Service Provider
        |
        +---- Repository Bindings
        |
        +---- Routes
        |
        +---- Other Module Services
```

## 3. Laravel Bootstrap Registration

**File:** `bootstrap/providers.php`

```php
<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Core\Modules\ModuleServiceProvider::class,
];
```

`ModuleServiceProvider` is registered by Laravel during application boot.

Individual module providers are discovered dynamically.

## 4. Module Configuration

**File:** `config/modules.php`

This file contains the application's registered modules.

Example:

```php
return [
    App\Modules\Settings\SettingModule::class,

    App\Modules\Tenant\TenantModule::class,
    App\Modules\User\UserModule::class,
    App\Modules\Rbac\RbacModule::class,

    App\Modules\Product\ProductModule::class,
    App\Modules\Accounting\AccountingModule::class,
];
```

To enable a module, add its module class.

To temporarily disable a module, remove or comment out its class.

A module must implement `ModuleInterface`.

## 5. Module Interface

**File:** `app/Core/Modules/Contracts/ModuleInterface.php`

Every registered module must implement the following contract:

```php
interface ModuleInterface
{
    public static function name(): string;

    public static function permissions(): array;

    public static function serviceProviders(): array;

    public static function setupServices(): array;
}
```

### Method responsibilities

| Method | Responsibility |
|---|---|
| `name()` | Returns the module name |
| `permissions()` | Declares the module's permission catalog |
| `serviceProviders()` | Returns Laravel service providers |
| `setupServices()` | Returns tenant provisioning services |

## 6. Module Registry

**File:** `app/Core/Modules/ModuleRegistry.php`

`ModuleRegistry` reads module classes from `config/modules.php`.

It provides:

| Method | Purpose |
|---|---|
| `modules()` | Returns configured modules |
| `moduleNames()` | Returns valid module names |
| `permissions()` | Aggregates module permissions |
| `serviceProviders()` | Aggregates module service providers |
| `setupServices()` | Aggregates tenant setup services |

Only classes implementing `ModuleInterface` are accepted.

Duplicate permissions and service providers are removed.

## 7. Module Service Provider Registration

**File:** `app/Core/Modules/ModuleServiceProvider.php`

```php
<?php

namespace App\Core\Modules;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (ModuleRegistry::serviceProviders() as $provider) {
            $this->app->register($provider);
        }
    }
}
```

This provider dynamically registers the service providers declared by enabled modules.

No individual module provider needs to be added manually to `bootstrap/providers.php` when it is already registered through this mechanism.

## 8. RBAC Module Registration

**File:** `app/Modules/Rbac/RbacModule.php`

```php
<?php

namespace App\Modules\Rbac;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\Rbac\Providers\RbacServiceProvider;

class RbacModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'RBAC';
    }

    public static function permissions(): array
    {
        return [];
    }

    public static function serviceProviders(): array
    {
        return [
            RbacServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
```

RBAC is enabled in `config/modules.php`.

Its service provider is discovered and registered automatically.

## 9. RBAC Service Provider

**File:** `app/Modules/Rbac/Providers/RbacServiceProvider.php`

Responsibilities:

1. Register repository interface bindings.
2. Load RBAC API routes.

Repository bindings:

```php
$this->app->bind(
    RoleRepositoryInterface::class,
    RoleRepository::class
);

$this->app->bind(
    PermissionRepositoryInterface::class,
    PermissionRepository::class
);
```

Route registration:

```php
Route::middleware('api')
    ->prefix('api')
    ->group(__DIR__ . '/../Routes/api.php');
```

## 10. RBAC Route Registration

**File:** `app/Modules/Rbac/Routes/api.php`

All RBAC routes use the following middleware:

```php
Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->prefix('rbac')->group(function () {
    // RBAC endpoints
});
```

Middleware responsibilities:

| Middleware | Responsibility |
|---|---|
| `auth:sanctum` | Authenticates the user |
| `tenant` | Resolves and validates tenant context |
| `permission.tenant` | Sets Spatie's permission team ID |

The route file is loaded through `RbacServiceProvider`.

## 11. Tenant Setup Services

Tenant setup services are collected through:

```php
ModuleRegistry::setupServices();
```

The `TenantSetupService` orchestrates tenant provisioning.

For RBAC, the intended setup responsibilities are:

- Create default tenant roles.
- Assign permissions to default roles.
- Keep custom roles tenant-specific.

Global permission catalog creation is handled separately by the permission seeder.

## 12. Important Architecture Rules

1. Do not manually register an individual module provider if the module registry already registers it.
2. Every module must implement `ModuleInterface`.
3. Every enabled module must appear in `config/modules.php`.
4. Keep module service-provider registration centralized.
5. Repository bindings belong in the relevant module service provider.
6. Routes belong in the relevant module's route directory.
7. Tenant setup services must be orchestrated through tenant provisioning.
8. Do not duplicate module registration.
9. Module permissions should be declared through the module contract.
10. Keep module registration independent of business logic.

## 13. Current RBAC Registration Status

| Component | Status |
|---|---|
| `bootstrap/providers.php` | Verified |
| `config/modules.php` | Verified |
| `ModuleInterface` architecture | Verified |
| `ModuleRegistry` | Verified |
| `ModuleServiceProvider` | Verified |
| `RbacModule` | Verified |
| `RbacServiceProvider` | Updated with repository bindings |
| RBAC route registration | Verified by code inspection |
| Repository dependency injection at runtime | Pending testing |
| Duplicate route registration check | Pending testing |

## 14. Verification Commands

After completing the RBAC implementation, run:

```bash
php artisan optimize:clear
```

Check registered RBAC routes:

```bash
php artisan route:list --path=api/rbac
```

Check service container bindings:

```bash
php artisan tinker
```

Then:

```php
app(
    App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface::class
);

app(
    App\Modules\Rbac\Repositories\Contracts\PermissionRepositoryInterface::class
);
```

Both should resolve to their corresponding repository implementations.

---

**Document purpose:** This file serves as the architectural reference for module discovery, service-provider registration, dependency injection, route loading, and tenant setup in the Laravel ERP.