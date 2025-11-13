<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

class TenantManagerInvocationTest extends TestCase
{
    public function test_force_delete_calls_database_manager_delete(): void
    {
        // Prepare a tenant record in the central DB
        $tenantId = (string) Str::ulid();
        $dbName = 'tenant_test_' . substr(md5((string) Str::ulid()), 0, 8);

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Acme Corp',
            // db_name is required by the tenants table; also include it inside data JSON so the VirtualColumn returns it as an internal key.
            'db_name' => $dbName,
            'data' => json_encode(['db_name' => $dbName]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $tenant = \App\Models\Tenant::find($tenantId);
    $this->assertNotNull($tenant);

    // Ensure the internal db_name is set so DatabaseConfig::getName() returns the expected name
    $tenant->setInternal('db_name', $dbName);
    $tenant->save();

        // Create a physical sqlite database file for this tenant so the real SQLiteDatabaseManager
        // will attempt to delete it when the tenant is force deleted.
        $dbPath = database_path($dbName);
        if (! file_exists($dbPath)) {
            file_put_contents($dbPath, '');
        }

    // Sanity: ensure the DatabaseConfig reports the same name we expect
    $tenantReloaded = \App\Models\Tenant::find($tenantId);
    $this->assertSame($dbName, $tenantReloaded->database()->getName());

    $this->assertFileExists($dbPath, 'Expected tenant database file to exist before forceDelete');

        // Force delete the tenant - the real manager should remove the DB file
        \App\Models\Tenant::withTrashed()->find($tenant->id)->forceDelete();

        $this->assertFileDoesNotExist($dbPath, 'Expected tenant database file to be removed by manager on forceDelete');
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }
}
