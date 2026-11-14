# Smart ERP API — Master Roadmap

Repository: https://github.com/codeslack/smart-erp-api

Last reviewed: 2026-11-14
Default branch: `main`

This document is the single source of truth for the current project status and planned delivery sequence. It deliberately separates code presence, module registration, workflow verification, automated test coverage, and production readiness. A module directory alone does not mean a feature is active or production-ready.

---

## 1. Product and architecture

Smart ERP API is a Laravel 13, API-first, multi-tenant ERP implemented as a modular monolith.

### Architecture standards

- Controller → Service → Repository → Model.
- Controllers handle HTTP concerns; services own business rules and orchestration; repositories own database queries; models define relationships and casts.
- Tenant-owned business records use tenant isolation and UUID-based public identifiers.
- Tenant resolution and middleware must run before authenticated tenant-scoped requests.
- Tenant scopes fail closed when tenant context is absent.
- Validation uses tenant-aware existence and uniqueness rules.
- Financial and inventory operations must be transactional, idempotent where appropriate, and safe under concurrent requests.
- Public APIs expose UUIDs rather than internal database IDs.
- Module permissions are registered through the module registry and enforced on protected routes.

### Module completion vocabulary

| Status | Meaning |
|---|---|
| Implemented | Core code exists for the stated capability. |
| Registered | Module is included in `config/modules.php`. |
| Workflow verified | End-to-end business workflow has been exercised successfully. |
| Test verified | Relevant automated tests pass in CI. |
| Production hardening | Security, concurrency, operational controls, observability and recovery still require review. |
| Planned | Not yet delivered as a complete capability. |

Do not infer a percentage from these labels without a measured module-by-module audit.

---

## 2. Current repository baseline

### Core platform

| Area | Current status | Notes |
|---|---|---|
| Tenant infrastructure | Implemented | Tenant manager/resolver, bootstrap and tenant middleware, fail-closed tenant scoping. |
| Authentication and users | Implemented | Sanctum-based API authentication and tenant-aware user handling. |
| RBAC | Implemented | Spatie permissions with tenant/team context, tenant roles, permission setup and UUID API identifiers. |
| Module registry | Implemented | Central module, provider, permission and tenant setup-service registration. |
| API response/request foundation | Implemented | Shared API response and request conventions. |

### Master data and operations

| Domain | Current status | Notes |
|---|---|---|
| Customers and suppliers | Implemented | Separate party records and opening-bill workflows. |
| Product, category, brand, unit and product-unit | Implemented | Core product and master-data capabilities. |
| Areas, warehouses and payment terms | Implemented | Tenant setup and master data. |
| Inventory V4 | Stable implementation checkpoint | Product stock, stock ledger, stock adjustments/transfers, valuation and batch/serial/expiry capabilities; continue production hardening. |
| Opening stock | Implemented | Opening inventory entry capability. |
| Purchase Order | Implemented | Draft/approval workflow and GRN progression. |
| Goods Receipt Note (GRN) | Implemented | Partial receipt and pending quantity tracking. GRN must not directly write stock balances or stock ledger; stock is posted through the purchase approval workflow. |
| Purchase | Implemented | GRN conversion, approval and stock posting. Direct Purchase Bill entry is also supported. |
| Purchase Return | Implemented | Return tracking and stock reversal. |
| Sales Quotation | Implemented | Approval and conversion workflow. |
| Sales Order | Implemented | Approval, pending quantity tracking and delivery/sale progression. |
| Delivery Note | Code exists; registration needs verification | Its module directory exists but it is not listed in the current module registry. Validate route and workflow ownership before activation. |
| Sales | Implemented | Approval, stock deduction and accounting posting. |
| Sales Return | Implemented | Stock reversal and accounting reversal using saved transaction snapshots. |

### Accounting, tax and payments

| Domain | Current status | Notes |
|---|---|---|
| Chart of Accounts | Implemented | Core account structure and accounting mappings. |
| Journal Entries | Implemented | Journal voucher and posting foundation. |
| General Ledger | Implemented | General ledger posting and account balances. |
| Trial Balance | Implemented | Accounting balance report foundation. |
| Profit & Loss | Implemented | Existing report foundation; improve drill-down and validation. |
| Balance Sheet | Implemented | Existing report foundation; improve classification and reconciliation. |
| Cash Flow | Implemented | Existing report foundation; improve report validation and reconciliation. |
| Tax V4 | Stable implementation checkpoint | Tax applicability, tax snapshots, tax account mapping and sales-return reversal of saved tax components. |
| Payment V4A | Implemented | Unified Payment module replacing the legacy Receipt implementation. |
| Advance Allocation | Test verified | Customer/supplier advance allocation lifecycle; customer-advance allocation and reversal serialize on the same payment-row lock, with lifecycle regression coverage. |
| Multi-currency sales accounting | Implemented | Transaction/base-currency values and exchange-rate-aware accounting posting. PR #28 merged after CI passed. |

### Latest integration checkpoints

- PR #28, **Fix Sales tax and multi-currency accounting**, is merged into `main`.
- PR #21, **Prevent reversing customer advances while allocated to Sales**, is merged into `main` (merge commit `da39ffe`). Its final rebased head passed targeted lifecycle tests (2 tests / 19 assertions) and the full suite (733 tests / 3,484 assertions) in GitHub Actions.
- The latest observed main-branch CI run after PR #28 passed.

These are repository checkpoints, not a claim that every production-readiness requirement is complete.

---

## 3. Module registration audit

The current `config/modules.php` registers 30 modules.

The following module directories exist but are not currently registered there:

- `DeliveryNote`
- `DocumentNumber`
- `StockAdjustment`
- `StockTransfer`

Before enabling any of them, verify service-provider registration, route loading, permission definitions, tenant setup requirements, dependencies, and automated tests. In particular, confirm whether stock adjustment and transfer functionality is intentionally owned by the Inventory module before registering separate modules.

Do not register a module solely because its directory exists.

### P1 registration audit findings

- **Delivery Note:** remains unregistered. Its legacy service uses race-prone `MAX(id) + 1` numbering, `abort()`-based business validation, and incorrect delivery-quantity handling. It also creates Sales records directly instead of using the complete Sale workflow. Do not activate it until it is rebuilt and covered by end-to-end tests.
- **Stock Adjustment and Stock Transfer:** remain unregistered. Their models reference an obsolete `TenantModel` namespace, and both services depend on an `InventoryService` class that is absent from the current Inventory module. Their approval paths are not compatible with the current `InventoryPostingService` contract. Do not activate them until rebuilt on the current inventory posting and reversal services.
- These directories are legacy scaffolding, not active capabilities. Their audit is complete; their implementation and activation remain separate future work.

---

## 4. Immediate priorities

### P0 — Customer advance payment lifecycle (completed)

1. Rebased PR #21 onto the latest `main` and merged it after all required checks passed.
2. `allocateCustomerAdvance()` and payment reversal now lock the same source Payment row inside their respective database transactions.
3. Allocation rechecks customer-advance purpose and `CONFIRMED` status after acquiring the lock; reversal rejects payments that still have Sales allocations.
4. Regression coverage verifies both safe lifecycle orderings: allocation first prevents reversal; reversal first prevents allocation. Rejected operations leave payment status, allocation records and Sale paid/due balances consistent.
5. Final PR head `a9421f34c652069007b2cfd02a82de404b789f81`: targeted tests **2 passed (19 assertions)**; full suite **733 passed (3,484 assertions)**; GitHub Actions successful.
6. Merge commit: `da39ffe2fae1100014b9da025ad993131528e189`.

### P1 — Inventory and document lifecycle hardening

1. Review negative-stock policy and enforce it consistently.
2. Use database row locking (including `lockForUpdate()` where appropriate) around stock balance mutations.
3. Verify transaction boundaries and rollback behavior for posting and reversal.
4. Prevent duplicate stock/accounting posting on retries.
5. Verify FIFO/WAC valuation, batch, serial, expiry and unit conversion edge cases.
6. Audit Delivery Note, Stock Adjustment and Stock Transfer registration and workflow coverage.
7. Verify that GRN receipt does not bypass the Purchase posting boundary.

**Coordinated implementation:** PR #30, `Harden inventory and document lifecycle reconciliation`, was merged into `main` as `a14c4401441ce6204443cf6a8eb0830296c15db2`. Its exact PR head passed CI (**763 tests, 3,613 assertions**); local verification of the merged `main` branch is pending. It includes:

- ProductStock uniqueness for nullable and non-null variants, savepoint-based duplicate recovery, and soft-deleted balance restoration.
- Weighted-average negative-stock recovery, FIFO insufficient-layer rejection, and stock posting/reversal regression coverage.
- GRN stock posting at Purchase approval, partial-bill idempotency, batch/serial allocation, expiry metadata consistency, and expired-batch issue prevention.
- Batch availability is restricted to active, unexpired batches; expiry dates are validated at receipt, and expired batches are blocked for sales, transfers and manufacturing consumption.
- The recorded expiry date remains valid through that calendar day; sales, transfers and manufacturing consumption are blocked from the following day.
- Purchase landed-cost allocation by value, quantity, or equal allocation, with item-level valuation and accounting reconciliation.
- Purchase/return currency snapshots, input-tax account mapping, expense/refund account-type validation, and subledger reconciliation coverage.
- Supplier advance allocation/reversal locking and ownership regression coverage.

Delivery Note, Stock Adjustment and Stock Transfer remain intentionally unregistered; their legacy implementations are not activated by this PR.

Do not mark P1 complete until the merged code is verified locally.

### P1 — Accounting and tax reconciliation

1. Reconcile source documents to journal entries and general ledger balances.
2. Verify customer and supplier advance allocations and reversals.
3. Verify tax snapshots, inclusive/exclusive tax calculations, rounding and return reversals.
4. Verify multi-currency transaction values, exchange rates and base-currency postings.
5. Verify opening balances, control accounts and subledger-to-general-ledger reconciliation.

### P2 — Essential accounting reports

Build and verify reports in this order:

1. Account Ledger (account drill-down, opening balance, debit/credit movement and running balance).
2. Journal Register (date, voucher number, status and source filters).
3. Customer Ledger (customer-wise running balance and source references).
4. Supplier Ledger (supplier-wise running balance and source references).
5. Receivable and Payable Aging.
6. Account Balance Summary.
7. Day Book.
8. GST/VAT Tax Reports.
9. Improved Trial Balance, Profit & Loss and Balance Sheet with drill-down and reconciliation.
10. Financial Dashboard KPIs.

Reports must respect tenant scope, fiscal period, posting status and base-currency conventions. Report totals must reconcile to the underlying ledger.

### P2 — Auditability and operations

- Document creation/update/approval/reversal audit trail.
- User activity and security-relevant event logging.
- Approval history and actor attribution.
- Document attachments for purchase, sales and return records.
- Backup/restore procedure and recovery verification.
- Structured logs, error reporting and operational health checks.

---

## 5. Production-readiness gates

Before calling the core ERP production-ready, complete and record evidence for each gate:

### Tenant and authorization

- Cross-tenant read, update, delete and relationship-injection tests.
- Missing-tenant and invalid-tenant fail-closed tests.
- Authorization coverage for every protected route and action.
- Tenant-aware unique and exists validation.
- Permission-team context cleanup after success and exceptions.

### Financial correctness

- Every confirm/post action creates the expected journal entries exactly once.
- Reversals use original posted snapshots and mapped accounts rather than recalculating historical tax or valuation.
- Debit and credit totals balance.
- Customer/supplier subledgers reconcile with control accounts.
- Foreign-currency amounts and base-currency amounts reconcile using stored exchange rates.
- Rounding and decimal precision are tested at document and ledger level.

### Inventory correctness

- Stock balance equals the sum of ledger movements.
- Concurrent posting cannot oversell or create incorrect balances.
- Reversal restores the correct quantity and valuation.
- Batch, serial, expiry, warehouse and unit-conversion rules are enforced.
- Draft documents do not change stock or accounting balances.

### Engineering and operations

- Full automated test suite passes in CI.
- Migration and seed process succeeds on a clean database.
- API contract and Postman coverage are maintained.
- Backup and restore are tested.
- Deployment, environment configuration and rollback procedures are documented.

---

## 6. Upcoming delivery roadmap

| Sequence | Milestone | Exit criteria |
|---|---|---|
| 1 | Payment lifecycle completion | PR #21 refreshed, fully tested, reviewed and merged. |
| 2 | Inventory/document hardening | Concurrency, negative stock, duplicate posting and reversal tests pass. |
| 3 | Accounting/tax reconciliation | Document-to-ledger, tax, advance and FX reconciliation tests pass. |
| 4 | Accounting reports | Account Ledger and core subledger reports reconcile to GL. |
| 5 | Audit and operations | Audit trail, backups, restore and deployment checks verified. |
| 6 | Production readiness | All production-readiness gates have recorded evidence. |
| 7 | ERP expansion | Start new business domains only after the core ERP gates are met. |

---

## 7. Long-term expansion

These are future product areas, not part of the current core completion claim.

### CRM
- Leads, opportunities, activities, follow-ups and customer pipeline.

### HRM and Payroll
- Employee records, attendance, leave, payroll runs, statutory deductions and payslips.

### Manufacturing
- Bill of Materials (BOM), production orders, material consumption, finished goods, work-in-progress and production costing.

Additional future candidates include budgeting, fixed assets, expense management, advanced analytics and integrations. Scope each as a separate roadmap proposal after core production readiness.

---

## 8. Roadmap maintenance rules

- Update this document whenever a milestone changes status.
- Mark work as implemented only when the capability exists in the current branch.
- Mark work as workflow verified only when its end-to-end workflow has been exercised.
- Mark work as test verified only when relevant automated tests pass.
- Keep open PR work separate from merged `main` functionality.
- Do not publish a single project-completion percentage without a defined weighted measurement across modules and production-readiness gates.
- Keep this file as the authoritative roadmap; remove stale duplicate TODO and design scratch documents rather than maintaining competing plans.
