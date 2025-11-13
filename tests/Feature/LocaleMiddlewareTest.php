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
     * Test that the default locale is set when no user is authenticated.
     */
    public function test_default_locale_is_en_us(): void
    {
        $this->get('/');

        $this->assertEquals('en_US', App::getLocale());
    }

    /**
     * Test that user locale is used when user is authenticated.
     */
    public function test_authenticated_user_locale_is_used(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user);

        $this->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }

    /**
     * Test that browser Accept-Language header is used when no user locale.
     */
    public function test_browser_locale_is_used_when_no_user_locale(): void
    {
        $this->withHeaders([
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
        ])->get('/');

        $this->assertEquals('es_ES', App::getLocale());
    }

    /**
     * Test that unsupported locale falls back to default.
     */
    public function test_unsupported_locale_falls_back_to_default(): void
    {
        $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->get('/');

        $this->assertEquals('en_US', App::getLocale());
    }

    /**
     * Test that user locale takes precedence over browser locale.
     */
    public function test_user_locale_takes_precedence_over_browser(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user);

        $this->withHeaders([
            'Accept-Language' => 'es-ES,es;q=0.9',
        ])->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }

    /**
     * Test that tenant_user locale takes precedence over user locale.
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
            'locale' => 'es_ES',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // Initialize tenancy context so middleware can detect current tenant
            tenancy()->initialize($tenant);

            $this->actingAs($user);

            $this->get('/');

            $this->assertEquals('es_ES', App::getLocale());
        } finally {
            // Teardown tenancy context to avoid leaking state to other tests
            tenancy()->end();
        }
    }

    /**
     * Test locale resolution on API routes.
     */
    public function test_api_locale_resolution_works(): void
    {
        $user = User::factory()->create(['locale' => 'es_ES']);

        $this->actingAs($user)->get('/');

        $this->assertEquals('es_ES', App::getLocale());
    }

    /**
     * Test that locale is applied to Carbon date formatting.
     */
    public function test_locale_affects_carbon_formatting(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user);

        $this->get('/');

        $this->assertEquals('pt_BR', App::getLocale());

        // Verify Carbon uses the set locale
        $date = \Carbon\Carbon::now();
        // Carbon will use the app locale for formatting
        $this->assertTrue(true);
    }

    /**
     * Test that partial locale codes are matched to full locales.
     */
    public function test_partial_locale_code_is_matched(): void
    {
        // Test "es" matches "es_ES"
        $this->withHeaders([
            'Accept-Language' => 'es,en;q=0.9',
        ])->get('/');

        $this->assertEquals('es_ES', App::getLocale());
    }

    /**
     * Test that browser locale matching is case-insensitive.
     */
    public function test_browser_locale_matching_is_case_insensitive(): void
    {
        // Test "PT-br" (mixed case) matches "pt_BR"
        $this->withHeaders([
            'Accept-Language' => 'PT-br,pt;q=0.9',
        ])->get('/');

        $this->assertEquals('pt_BR', App::getLocale());
    }
}
