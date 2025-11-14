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
     * Ensure migrations for required connections run before each test.
     *
     * Running migrations per-test increases isolation at the cost of speed,
     * but keeps the test suite hermetic in multi-connection setups. The
     * previous static-flag approach caused intermittent issues and weaker
     * isolation, so we prefer the safer per-test migrations.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations per-test to ensure isolation across the test suite.
        // RefreshDatabase handles the default connection, but explicitly run
        // migrations for the 'central' connection as well.
        $this->artisan('migrate', ['--force' => true]);
        $this->artisan('migrate', ['--database' => 'central', '--force' => true]);
    }
}
