<?php

namespace Tests\Feature;

use App\Jobs\ProvisionTenantJob;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TenantProvisionCommandAsyncTest extends TestCase
{
    public function test_command_with_async_dispatches_job()
    {
        Bus::fake();

        $this->artisan('dens:provision-tenant', ['name' => 'Async Co', '--async' => true])
            ->assertExitCode(0);

        Bus::assertDispatched(ProvisionTenantJob::class);
    }
}
