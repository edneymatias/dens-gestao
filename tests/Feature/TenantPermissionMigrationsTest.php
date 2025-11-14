<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantPermissionMigrationsTest extends TestCase
{
    public function test_permission_tables_exist_after_tenant_provision(): void
    {
        $tenantName = 'Permissions Co '.Str::random(4);

        $this->artisan('dens:provision-tenant', ['name' => $tenantName])
            ->assertExitCode(0);

        $tenant = Tenant::query()->where('name', $tenantName)->first();
        $this->assertNotNull($tenant, 'Expected tenant to be created after provision command.');

        tenancy()->initialize($tenant);

        try {
            $tables = [
                'permissions',
                'roles',
                'model_has_permissions',
                'model_has_roles',
                'role_has_permissions',
            ];

            foreach ($tables as $table) {
                $this->assertTrue(
                    Schema::hasTable($table),
                    sprintf('Expected tenant database to contain %s table.', $table)
                );
            }
        } finally {
            tenancy()->end();

            // Clean up tenant artifacts created by this test
            if ($tenant->exists) {
                $tenant->forceDelete();
            }
        }
    }
}
