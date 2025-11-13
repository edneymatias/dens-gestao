<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Connections to include in test transactions.
     *
     * @var array
     */
    protected $connectionsToTransact = ['central'];

    /**
     * Ensure migrations for required connections run once per test-suite.
     *
     * Running migrations repeatedly on every test can be expensive; use a
     * static flag so migrations for extra connections run only once.
     *
     * Note: RefreshDatabase handles the default connection behavior, but in
     * multi-connection setups we explicitly ensure the 'central' connection
     * migrations are applied deterministically for the suite.
     *
     * @var bool
     */
    protected static bool $migrationsRunForSuite = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrationsRunForSuite) {
            // Run migrations for the default connection (deterministic in some CI envs)
            $this->artisan('migrate', ['--force' => true]);

            // Run migrations specifically for the central connection used by tenancy metadata
            $this->artisan('migrate', ['--database' => 'central', '--force' => true]);

            self::$migrationsRunForSuite = true;
        }
    }
}
