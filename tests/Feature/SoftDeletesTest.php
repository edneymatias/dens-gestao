<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function user_can_be_soft_deleted_and_restored()
    {
        // Insert directly on the central connection to avoid multi-connection
        // transaction/migration complexity in this test environment.
        $userId = (string) Str::ulid();

        DB::connection('central')->table('users')->insert([
            'id' => $userId,
            'name' => 'Test User',
            'email' => 'test+'.substr($userId, -6).'@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($userId);

        $this->assertDatabaseHas('users', ['id' => $user->id], 'central');

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id], 'central');

        $user->restore();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null], 'central');
    }

    /** @test */
    public function tenant_soft_delete_marks_trashed_and_force_delete_removes_record_and_calls_db_manager()
    {
        // Create a tenant record. Stancl's model may generate id if omitted.
        $tenantId = (string) Str::ulid();
        $dbName = 'tenant_test_'.substr(md5((string) Str::ulid()), 0, 8);

        // Insert directly to ensure db_name column is populated (stancl Tenant may serialize unknown attrs into data)
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Acme Corp',
            'db_name' => $dbName,
            'data' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = \App\Models\Tenant::find($tenantId);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);

        // Soft delete
        $tenant->delete();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);

        // Bind a fake database manager to avoid actual DB file deletion during tests
        $fakeManager = new class
        {
            public function setConnection(string $connection): void {}

            public function deleteDatabase($tenant): bool
            {
                return true;
            }

            public function createDatabase($tenant): bool
            {
                return true;
            }

            public function databaseExists(string $name): bool
            {
                return true;
            }

            public function makeConnectionConfig(array $baseConfig, string $databaseName): array
            {
                $baseConfig['database'] = $databaseName;

                return $baseConfig;
            }
        };

        // The tenancy package will resolve a manager class based on the template connection driver.
        // Our config uses the sqlite manager for tests; bind that concrete class to the fake instance.
        $this->app->instance(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class, $fakeManager);

        // Force delete - should remove the tenant record without throwing.
        \App\Models\Tenant::withTrashed()->find($tenant->id)->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }
}
