<?php

namespace Tests\Unit;

use App\Services\TenantProvisionService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class TenantProvisionServiceFailureTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_database_failure_marks_failed_and_cleans_up()
    {
        // Mock the tenant database manager to throw on createDatabase
        $managerClass = \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class;

        $managerMock = Mockery::mock($managerClass);
        $managerMock->shouldReceive('setConnection')->andReturnNull();
        $managerMock->shouldReceive('createDatabase')->once()->andThrow(new \RuntimeException('create db failed'));

        $this->app->instance($managerClass, $managerMock);

        // Ensure Artisan migrate isn't called because createDatabase fails before that
        Artisan::shouldReceive('call')->never();

        /** @var TenantProvisionService $service */
        $service = $this->app->make(TenantProvisionService::class);

        $this->expectException(\RuntimeException::class);

        try {
            $service->provision(['name' => 'Failing Inc.'], true, false);
        } finally {
            // Ensure tenant record was removed (cleanup attempted)
            $this->assertDatabaseMissing('tenants', ['name' => 'Failing Inc.']);
        }
    }
}
