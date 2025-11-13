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

    protected function setUp(): void
    {
        parent::setUp();

        // The 'central' connection is included in $connectionsToTransact so
        // RefreshDatabase will handle migrations for it. No need to run
        // migrate:fresh on every test which is expensive.
    }
}
