<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

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

        // Ensure migrations also run for the 'central' connection which some models use.
        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }
}
