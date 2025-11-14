<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class InitializeTenancyBySessionTest extends TestCase
{
    public function test_session_tenant_initializes_context_and_updates_cache_prefix(): void
    {
        $uri = '/__tenant-session-valid';

        Route::middleware('web')->get($uri, function () {
            return response()->json([
                'tenant' => optional(tenancy()->tenant)->getTenantKey(),
                'cache_prefix' => config('cache.prefix'),
            ]);
        });

        $tenant = Tenant::query()->create([
            'name' => 'Session Tenant',
            'db_name' => 'tenant_'.Str::lower(Str::random(10)),
        ]);

        $basePrefix = config('cache.prefix');

        $response = $this->withSession(['tenant_id' => $tenant->getTenantKey()])->get($uri);

        $response->assertOk();
        $response->assertJson([
            'tenant' => $tenant->getTenantKey(),
        ]);

        $this->assertStringContainsString($tenant->getTenantKey(), $response->json('cache_prefix'));
        $this->assertFalse(tenancy()->initialized, 'Tenancy should be ended after the request completes.');
        $this->assertSame($basePrefix, config('cache.prefix'));
    }

    public function test_invalid_session_tenant_is_cleared_and_does_not_initialize_context(): void
    {
        $uri = '/__tenant-session-invalid';

        Route::middleware('web')->get($uri, function () {
            return response()->json([
                'tenant' => optional(tenancy()->tenant)->getTenantKey(),
                'cache_prefix' => config('cache.prefix'),
            ]);
        });

        $basePrefix = config('cache.prefix');

        $response = $this->withSession(['tenant_id' => 'missing-tenant'])->get($uri);

        $response->assertOk();
        $response->assertJson([
            'tenant' => null,
        ]);
        $response->assertSessionMissing('tenant_id');

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame($basePrefix, config('cache.prefix'));
    }
}
