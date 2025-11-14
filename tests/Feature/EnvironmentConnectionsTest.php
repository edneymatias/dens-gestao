<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentConnectionsTest extends TestCase
{
    public function test_default_db_connection_is_central()
    {
        // The application configuration should use 'central' as the default
        $this->assertSame('central', Config::get('database.default'));

        // Ensure the central connection config exists
        $this->assertNotNull(Config::get('database.connections.central'));

        // The central connection should be available and we can get a PDO instance
        $pdo = DB::connection('central')->getPdo();
        $this->assertNotNull($pdo);

        // The phpunit env should set DB_CENTRAL_DATABASE to :memory: (fast tests)
        $this->assertSame(':memory:', env('DB_CENTRAL_DATABASE'));
    }
}
