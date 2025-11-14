<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service responsible for provisioning tenants.
 *
 * This class orchestrates tenant creation, credentials generation,
 * database creation (via stancl managers) and running tenant migrations/seeders.
 */
class TenantProvisionService
{
    /**
     * Provision a tenant.
     *
     * @param  array  $data  ['name' => string, 'id' => ?string]
     *
     * @throws Throwable
     */
    public function provision(array $data, bool $migrate = true, bool $seed = false): Tenant
    {
        $name = $data['name'] ?? null;

        if (! $name) {
            throw new InvalidArgumentException(__('services.tenant_name_required'));
        }

        $id = $data['id'] ?? null;

        // Use the model's connection to run a short transaction for creating the tenant record
        // and persisting credentials. We avoid keeping long transactions open during
        // external operations (createDatabase / migrations).
        $tenant = null;

        // Ensure we have an id (ULID) so we can compute a db_name prior to inserting
        if (! $id) {
            $id = (string) Str::ulid();
        }

        // Compute a safe db_name using the tenancy prefix/suffix and the tenant id.
        $dbName = config('tenancy.database.prefix').$id.config('tenancy.database.suffix');

        $connection = Tenant::query()->getModel()->getConnection();

        try {
            $connection->transaction(function () use (&$tenant, $id, $name, $dbName) {
                $tenant = Tenant::query()->create(array_filter([
                    'id' => $id,
                    'name' => $name,
                    'db_name' => $dbName,
                ]));

                // Generate and persist any missing credentials / db_name on the tenant model.
                // makeCredentials() will only save additional generated credentials when the tenant exists.
                $tenant->database()->makeCredentials();
            });
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed during creation transaction: '.$e->getMessage());

            // Nothing external (DB server) created yet in this stage, so just rethrow.
            throw $e;
        }

        // Ensure tenant was created inside the transaction.
        if (! $tenant) {
            throw new RuntimeException(__('services.tenant_model_not_created'));
        }

        // Mark as provisioning so reconcilers / UI can show progress.
        try {
            $tenant->provision_state = 'provisioning';
            $tenant->save();
        } catch (Throwable $e) {
            Log::warning('Failed to set provision_state=provisioning: '.$e->getMessage());
        }

        // Now create the physical database via the manager (outside the transaction).
        try {
            $tenant->database()->manager()->createDatabase($tenant);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed creating physical database: '.$e->getMessage());

            // Attempt cleanup: remove tenant record if DB creation failed.
            try {
                if (isset($tenant) && $tenant->exists) {
                    $tenant->forceDelete();
                }
            } catch (Throwable $cleanupEx) {
                Log::error('Cleanup after failed physical DB creation also failed: '.$cleanupEx->getMessage());
            }

            throw $e;
        }

        try {
            if ($migrate) {
                Artisan::call('tenants:migrate', [
                    '--tenants' => [$tenant->getTenantKey()],
                ]);
            }

            if ($seed) {
                Artisan::call('tenants:seed', [
                    '--tenants' => [$tenant->getTenantKey()],
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed during migrate/seed: '.$e->getMessage());

            // Attempt cleanup: delete DB and tenant record and mark failed.
            try {
                if ($tenant && $tenant->exists) {
                    $tenant->provision_state = 'failed';
                    $tenant->save();
                    $tenant->database()->manager()->deleteDatabase($tenant);
                    $tenant->forceDelete();
                }
            } catch (Throwable $cleanupEx) {
                Log::error('Cleanup after failed migrate/seed also failed: '.$cleanupEx->getMessage());
            }

            throw $e;
        }

        // Mark success
        try {
            $tenant->provision_state = 'provisioned';
            $tenant->save();
        } catch (Throwable $e) {
            Log::warning('Failed to set provision_state=provisioned: '.$e->getMessage());
        }

        return $tenant;
    }

    /**
     * Complete provisioning for an existing tenant record.
     * This is used by the reconciler to retry tenants stuck in 'provisioning'.
     *
     *
     *
     * @throws Throwable
     */
    public function completeProvision(Tenant $tenant, bool $migrate = true, bool $seed = false): Tenant
    {
        // Ensure credentials exist
        $tenant->database()->makeCredentials();

        // Mark as provisioning
        try {
            $tenant->provision_state = 'provisioning';
            $tenant->save();
        } catch (Throwable $e) {
            Log::warning('Failed to set provision_state=provisioning on completeProvision: '.$e->getMessage());
        }

        $manager = $tenant->database()->manager();
        $dbName = $tenant->database()->getName();

        try {
            if (! $manager->databaseExists($dbName)) {
                $manager->createDatabase($tenant);
            }
        } catch (Throwable $e) {
            Log::error('Reconciler failed to create physical database: '.$e->getMessage());
            $tenant->provision_state = 'failed';
            $tenant->save();
            throw $e;
        }

        try {
            if ($migrate) {
                Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getTenantKey()]]);
            }

            if ($seed) {
                Artisan::call('tenants:seed', ['--tenants' => [$tenant->getTenantKey()]]);
            }
        } catch (Throwable $e) {
            Log::error('Reconciler failed during migrate/seed: '.$e->getMessage());
            $tenant->provision_state = 'failed';
            $tenant->save();

            throw $e;
        }

        try {
            $tenant->provision_state = 'provisioned';
            $tenant->save();
        } catch (Throwable $e) {
            Log::warning('Failed to set provision_state=provisioned in completeProvision: '.$e->getMessage());
        }

        return $tenant;
    }
}
