Plan: Audit stancl/tenancy integration (for dens-gestao)

Purpose

-   Audit the current stancl/tenancy multi-db integration so tenant migrations are applied during provisioning.
-   Identify where our implementation diverges from stancl/tenancy expectations and recommend fixes.

One-line goal

-   Ensure `tenants:migrate` finds and applies tenant migrations as part of provisioning and reconciler flows.

Immediate scope

-   Inspect `config/tenancy.php`, `app/Services/TenantProvisionService.php`, `app/Models/Tenant.php`, and scripts (`scripts/provision_tenant.php`).
-   Inspect vendor `stancl/tenancy` implementation for `tenants:migrate` and `TenantDatabaseManagers\SQLiteDatabaseManager::createDatabase`.
-   Compare to stancl/tenancy docs for migration discovery, `--path`/`--realpath` behavior, and sqlite manager behavior.

Assumptions

-   Tenant DBs are sqlite files created under `database/` with names matching `config('tenancy.database.prefix') . $id`.
-   Provisioning uses `createDatabase()` then `Artisan::call('tenants:migrate', ['--tenants' => [$id]])`.

Steps (what I'll do)

1. Read and summarize our config & service code to extract how migration command is invoked and how DBs are created.
2. Inspect vendor command implementation to learn how `tenants:migrate` discovers migration files and how it uses `migration_parameters` from config.
3. Inspect vendor SQLite manager behavior: does it create zero-byte files or initialize DB schema?
4. Compare config `migration_parameters` with the actual invocation in our service; note mismatches such as missing `--path`/`--realpath` or template connection.
5. Identify spots where tenancy bootstrap or template connection is not set, causing migrator to skip tenant migrations.
6. Produce prioritized fixes (file path, symbol/function, and exact change description) and a runnable debug checklist.

Deliverables

-   A short TL;DR and structured audit: evidence, root causes, recommended fixes, debug checklist, references.
-   A prioritized list of code/config edits to fix provisioning.

Quick checklist (for later validation)

-   Run `php artisan config:clear` to ensure package config is not cached (permission migration depends on `config('permission')`).
-   Re-run provisioning: `php scripts/provision_tenant.php <tenant-id>` and check `database/tenant_<id>` size and `.tables`.
-   Run verbose migrate with explicit path: `php artisan tenants:migrate --tenants=<id> --path=$(pwd)/database/migrations/tenant --realpath -vvv`

Notes for the author

-   If `tenants:migrate` still says "Nothing to migrate", inspect the vendor migrator to see whether it filters migrations by published path or expects migrations to be under a specific namespace.
-   Look for places where we may have overridden `migration_parameters` or `template_tenant_connection` in code; ensure `template_tenant_connection` points to a connection that has the same driver/schema as the tenant (or is null to let managers define it).

Status

-   TODO: run `php artisan config:clear` and reprovision (next step after confirmation).
