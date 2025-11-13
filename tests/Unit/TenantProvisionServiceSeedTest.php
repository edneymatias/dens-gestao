<?php

namespace Tests\Unit;

use App\Services\TenantProvisionService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class TenantProvisionServiceSeedTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_provision_with_seed_calls_tenants_seed()
    {
        // Mock the tenant database manager so DB creation succeeds
        $managerClass = \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class;

        $managerMock = Mockery::mock($managerClass);
        $managerMock->shouldReceive('setConnection')->andReturnNull();
        $managerMock->shouldReceive('createDatabase')->once()->andReturnTrue();

        $this->app->instance($managerClass, $managerMock);

        // Expect tenants:migrate and tenants:seed to be called
        Artisan::shouldReceive('call')
            ->with('tenants:migrate', Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['--tenants']);
            }))
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->with('tenants:seed', Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['--tenants']);
            }))
            ->andReturn(0);

        /** @var TenantProvisionService $service */
        $service = $this->app->make(TenantProvisionService::class);

        $tenant = $service->provision(['name' => 'Seeded Co'], true, true);

        $this->assertNotNull($tenant);
        $this->assertSame('provisioned', $tenant->provision_state);
    }
}
