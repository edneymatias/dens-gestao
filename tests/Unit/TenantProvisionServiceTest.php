<?php

namespace Tests\Unit;

use App\Services\TenantProvisionService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class TenantProvisionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_provision_happy_path()
    {
        // Mock the tenant database manager (SQLite manager is used by central connection in config)
        $managerClass = \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class;

        $managerMock = Mockery::mock($managerClass);
        $managerMock->shouldReceive('setConnection')->andReturnNull();
        $managerMock->shouldReceive('createDatabase')->once()->andReturnTrue();

        $this->app->instance($managerClass, $managerMock);

        // Mock Artisan calls for tenants:migrate
        Artisan::shouldReceive('call')
            ->with('tenants:migrate', Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['--tenants']);
            }))
            ->andReturn(0);

        /** @var TenantProvisionService $service */
        $service = $this->app->make(TenantProvisionService::class);

        $tenant = $service->provision(['name' => 'Acme Inc.'], true, false);

        $this->assertNotNull($tenant);
        $this->assertSame('provisioned', $tenant->provision_state);
        $this->assertNotEmpty($tenant->db_name);
    }
}
