# Modern Smart ERP

A modular, API-first Enterprise Resource Planning (ERP) backend built with **Laravel 13**. Modern Smart ERP is designed as a multi-tenant modular monolith with clear domain boundaries, tenant-aware data access, UUID-based public identifiers, and transactional business workflows.

The backend exposes JSON APIs. Business capabilities are organized into modules so that purchasing, sales, inventory, accounting, payments, tax, users, and administration can evolve independently while sharing a consistent application foundation.

## Architecture

### Application flow

Business operations follow one application flow:

```text
HTTP Request
    |
Middleware (authentication, tenant context, authorization)
    |
Form Request (input validation)
    |
Controller (HTTP orchestration only)
    |
Service / Action (business rules and transaction orchestration)
    |
Repository Interface
    |
Repository (database queries and persistence)
    |
Eloquent Model (relationships, casts, scopes)
    |
Database
```

Responsibilities are intentionally separated:

| Layer | Responsibility |
|---|---|
| Controller | Translate HTTP requests into service calls and return API resources/responses. No business rules or direct query logic. |
| Request | Validate and authorize incoming request data at the HTTP boundary. |
| Service / Action | Enforce business rules, coordinate domain operations, and manage transaction boundaries. |
| Repository | Encapsulate database reads and writes. No business workflow decisions. |
| Model | Define relationships, casts, model-level behavior, UUID identity, and tenant scoping. |

Use dependency injection and repository contracts where appropriate. Keep domain workflows explicit; do not put business logic in controllers or repositories.

## Core design principles

- **Modular monolith:** domain functionality lives in self-contained modules under `app/Modules`.
- **Multi-tenancy:** tenant-owned business records carry `tenant_id`; tenant-aware models and global scopes isolate tenant data.
- **Fail-closed tenant access:** tenant-scoped queries must not expose records when tenant context is missing.
- **UUID public identity:** APIs and route model binding use UUIDs; internal numeric primary keys remain implementation details.
- **Transactional workflows:** confirmation, cancellation, posting, reversal, and other financial or stock-changing operations must execute with appropriate database transactions and locking.
- **Separation of concerns:** controllers, requests, services/actions, repositories, and models have distinct responsibilities.
- **Consistent API contract:** use the shared API response and resource conventions for successful responses, validation errors, business errors, and pagination.
- **Explicit accounting and inventory effects:** drafts do not post financial or stock movements; confirmed workflows invoke their dedicated posting services.
- **Tenant-safe validation:** use tenant-aware validation rules for references and uniqueness checks.

## Multi-tenancy and authentication

Tenant context is established from the authenticated user's tenant association. Client-supplied tenant identifiers must not be trusted as authority for selecting a tenant.

The tenant infrastructure includes:

- Tenant resolution and scoped tenant context management.
- Bootstrap middleware that establishes tenant context around authenticated requests.
- Tenant middleware that rejects missing or invalid tenant context.
- Tenant-aware Eloquent models and global query scopes.
- Tenant-aware validation rules for existence and uniqueness.
- Tenant team context integration for Spatie Laravel Permission.

When adding a tenant-owned model:

1. Extend the project's tenant model base class.
2. Include the required tenant ownership and UUID conventions.
3. Ensure tenant context is present for creation and updates.
4. Use tenant-scoped relationships and validation.
5. Add tests proving that one tenant cannot read, update, or delete another tenant's records.

## Authorization

Role-based access control is implemented with **Spatie Laravel Permission** using tenant/team context.

Permissions are registered by domain modules and assigned through tenant setup and role-permission maps. Public role and permission identifiers use UUIDs.

Every protected endpoint must enforce its required permission through route middleware or an explicit policy/Gate check. Authentication and tenant middleware alone are not a substitute for authorization. Add both allowed-role and denied-role tests for each protected workflow.

## Module organization

Domain modules are located in `app/Modules`. A typical module contains some or all of the following:

```text
app/Modules/
└── ModuleName/
    ├── Controllers/
    ├── Requests/
    ├── Resources/
    ├── Models/
    ├── Repositories/
    │   └── Contracts/
    ├── Services/
    │   ├── Actions/
    │   ├── Calculators/
    │   ├── Posting/
    │   └── Validators/
    ├── Routes/
    │   └── api.php
    ├── Enums/
    ├── Providers/
    └── ModuleNameModule.php
```

The exact folders vary by domain complexity. Avoid creating empty layers or folders that add no value; preserve the architecture boundaries when a layer is present.

Module providers register module services, routes, and dependencies. Module definitions expose module metadata and permission declarations. Tenant setup services initialize tenant-specific defaults.

## Business domains

The repository contains domain modules for the ERP capabilities below. The implementation and completion status of each workflow should be verified from its module tests and documentation rather than inferred from this list.

- **Tenant and administration:** tenants, users, roles, permissions, settings, and setup.
- **Master data:** customers, suppliers, products, product categories, brands, units, warehouses, and related references.
- **Procurement:** purchase orders, goods receipt notes (GRN), purchase bills, and purchase returns.
- **Sales:** sales documents, customer returns, and related workflows.
- **Inventory:** stock balances, stock ledger, transfers, adjustments, batch/serial tracking, and valuation.
- **Accounting:** chart of accounts, journal entries, ledger, financial reports, and posting integrations.
- **Payments and advances:** payment records, allocations, and accounting postings.
- **Tax:** tax configuration, tax calculations, and tax-aware document workflows.
- **Reporting and supporting services:** document numbering, reports, and shared domain utilities.

## Inventory and accounting workflow rules

- Draft documents must not change on-hand stock or post accounting entries.
- Confirmation is the explicit point at which a workflow posts its inventory and/or accounting effects.
- Stock movements must go through the inventory posting services and maintain the stock ledger.
- Reversals must preserve traceability to the original transaction rather than silently rewriting history.
- Goods Receipt Notes update purchase-order receiving quantities and coordinate with the inventory workflow; GRN logic must not bypass the inventory posting boundary by directly editing stock balance tables.
- Accounting postings must use the accounting posting services and balanced journal-entry rules.
- Financial calculations should use database decimal values and precision-safe arithmetic. Avoid PHP floating-point arithmetic for money and quantity calculations where exact decimal behavior is required.

## API conventions

- API routes are registered by modules and exposed through the application's API route bootstrap.
- Use Form Requests for input validation.
- Use API Resources for shaping returned domain data.
- Expose UUIDs for public resource identifiers; do not expose internal database IDs as API identity.
- Use the shared API response conventions for success, message, data, and pagination metadata.
- Return business validation failures as structured API errors.
- Never accept `tenant_id`, approval identity, posting status, or other server-controlled fields as trusted client input unless a workflow explicitly requires and validates them.

## Technology

- PHP (see `composer.json` for the required runtime version)
- Laravel 13
- Laravel Sanctum for API authentication
- Spatie Laravel Permission for role and permission management
- Relational database supported by the Laravel application configuration
- PHPUnit / Laravel testing tools

The dependency manifest and lock file are the source of truth for exact package versions.

## Getting started

### Requirements

Install the PHP version and extensions required by `composer.json`, Composer, and a supported database server. Node.js is only required if you choose to build any frontend assets; this repository is an API backend.

### Install

```bash
git clone https://github.com/codeslack/smart-erp-api.git
cd smart-erp-api
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database connection and other environment-specific settings in `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
```

Start the local API server:

```bash
php artisan serve
```

The API base URL is normally `http://127.0.0.1:8000/api`. Confirm the actual route prefix and registered endpoints with:

```bash
php artisan route:list --path=api
```

### Testing

Run the full automated test suite:

```bash
php artisan test
```

Run a focused test file or test filter while developing:

```bash
php artisan test tests/Feature
php artisan test --filter=Tenant
```

Use a dedicated test database and the test environment configuration. Never point automated tests at production data.

## Development workflow

1. Inspect existing module conventions and migrations before adding schema.
2. Define or update the migration.
3. Implement the model and relationships.
4. Add enums and data objects when the domain requires them.
5. Define repository contracts and implementations for persistence needs.
6. Implement services/actions for business rules and transaction orchestration.
7. Add Form Requests and API Resources.
8. Add thin controllers and module routes.
9. Enforce authentication, tenant context, and endpoint permissions.
10. Add unit and feature tests for success, validation, tenant isolation, authorization, concurrency, and rollback behavior.
11. Run focused tests, then the full suite before committing.

Prefer modifying existing development migrations when the project is still pre-production and the schema has not been released. Once migrations are shared or deployed, use forward migrations rather than rewriting migration history.

## Security checklist for new features

- [ ] Authentication is required where appropriate.
- [ ] Tenant context is established before tenant-scoped access.
- [ ] Every protected endpoint enforces a permission or policy.
- [ ] Public identifiers use UUIDs.
- [ ] Tenant ownership cannot be supplied or changed by untrusted clients.
- [ ] Request validation checks referenced records within the active tenant.
- [ ] Mass assignment uses explicit, reviewed `$fillable` attributes.
- [ ] Server-controlled fields are assigned only by trusted application code.
- [ ] Transactions and row locks protect concurrent posting and allocation workflows.
- [ ] Tests cover cross-tenant access and unauthorized users.

## Contribution guidelines

Keep changes focused and consistent with the module's existing patterns. Do not bypass service workflows with direct controller queries or direct stock/accounting table updates. Include tests with behavior changes, and run the relevant test suite before opening a pull request.

## Project status

Modern Smart ERP is under active development. Module availability, workflow completeness, and production readiness must be assessed against the current implementation, migrations, and automated tests. Do not treat a listed domain as a guarantee that every feature is production-ready.
