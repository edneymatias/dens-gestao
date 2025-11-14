<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Default locale when no user is authenticated.
     */
    public function test_default_locale_is_en(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $this->assertEquals('en', App::getLocale());
    }

    /**
     * Authenticated user's locale should be applied.
     */
    public function test_authenticated_user_locale_is_used(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }

    /**
     * Browser Accept-Language header should be used when user has no locale.
     */
    public function test_browser_locale_is_used_when_no_user_locale(): void
    {
        $this->withHeaders([
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
        ])->get('/');

        // 'es-ES' should map to canonical 'es'
        $this->assertEquals('es', App::getLocale());
    }

    /**
     * Unsupported browser locales should fall back to default.
     */
    public function test_unsupported_locale_falls_back_to_default(): void
    {
        $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->get('/');

        $this->assertEquals('en', App::getLocale());
    }

    /**
     * User locale takes precedence over browser locale.
     */
    public function test_user_locale_takes_precedence_over_browser(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)->withHeaders([
            'Accept-Language' => 'es-ES,es;q=0.9',
        ])->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }

    /**
     * tenant_user locale (central) should override user locale for a tenant.
     */
    public function test_tenant_user_locale_takes_precedence(): void
    {
        // Create a tenant record in the central DB
        $tenantId = (string) \Illuminate\Support\Str::ulid();

        \Illuminate\Support\Facades\DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Locale Test',
            'db_name' => 'tenant_test_'.substr(md5((string) \Illuminate\Support\Str::ulid()), 0, 8),
            'data' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = \App\Models\Tenant::find($tenantId);

        $user = User::factory()->create(['locale' => 'pt_BR']);

        // Insert a tenant_user row that overrides the user's locale for this tenant
        \Illuminate\Support\Facades\DB::connection('central')->table('tenant_user')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => 'member',
            'locale' => 'es',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            tenancy()->initialize($tenant);

            $this->actingAs($user)->get('/');

            $this->assertEquals('es', App::getLocale());
        } finally {
            tenancy()->end();
        }
    }

    /**
     * API route locale resolution - ensure middleware works with API requests.
     */
    public function test_api_locale_resolution_works(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->get('/');

        $this->assertEquals('es', App::getLocale());
    }

    /**
     * Partial locale codes (language-only) should match supported locales.
     */
    public function test_partial_locale_code_is_matched(): void
    {
        $this->withHeaders([
            'Accept-Language' => 'es,en;q=0.9',
        ])->get('/');

        $this->assertEquals('es', App::getLocale());
    }

    /**
     * Browser locale matching should be case-insensitive and normalize separators.
     */
    public function test_browser_locale_matching_is_case_insensitive(): void
    {
        // 'PT-br' mixed case should match 'pt_BR'
        $this->withHeaders([
            'Accept-Language' => 'PT-br,pt;q=0.9',
        ])->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }
}
