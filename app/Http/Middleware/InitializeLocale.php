<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InitializeLocale
{
    /**
     * Handle an incoming request.
     *
     * Resolves locale in the following priority order:
     * 1. tenant_user.locale (if tenant context exists)
     * 2. users.locale (if user is authenticated)
     * 3. Browser Accept-Language header
     * 4. Fallback to en_US
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale && $this->isSupportedLocale($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Resolve the locale based on priority order.
     */
    protected function resolveLocale(Request $request): ?string
    {
        // Priority 1: tenant_user.locale (if in tenant context)
        if ($tenantUserLocale = $this->getTenantUserLocale($request)) {
            return $tenantUserLocale;
        }

        // Priority 2: users.locale (if user authenticated)
        if ($userLocale = $this->getUserLocale()) {
            return $userLocale;
        }

        // Priority 3: Browser Accept-Language header
        if ($browserLocale = $this->getBrowserLocale($request)) {
            return $browserLocale;
        }

        // Priority 4: Fallback to en_US
        return 'en_US';
    }

    /**
     * Get locale from tenant_user table if tenant context exists.
     */
    protected function getTenantUserLocale(Request $request): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        // Check if we're in a tenant context
        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return null;
        }

        try {
            // Query tenant_user table for locale
            $tenantUser = DB::connection('central')
                ->table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->first();

            return $tenantUser->locale ?? null;
        } catch (\Throwable $e) {
            // Silently fail if tenant_user table doesn't exist or query fails
            return null;
        }
    }

    /**
     * Get locale from authenticated user.
     */
    protected function getUserLocale(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return $user->locale ?? null;
    }

    /**
     * Get locale from browser Accept-Language header.
     */
    protected function getBrowserLocale(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (! $acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header and extract the first locale
        // Format: "en-US,en;q=0.9,pt-BR;q=0.8"
        $locales = explode(',', $acceptLanguage);

        foreach ($locales as $locale) {
            // Remove quality factor if present (e.g., ";q=0.9")
            $locale = trim(explode(';', $locale)[0]);

            // Convert "en-US" to "en_US" format
            $locale = str_replace('-', '_', $locale);

            if ($this->isSupportedLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Check if a locale is supported.
     */
    protected function isSupportedLocale(string $locale): bool
    {
        $supportedLocales = config('app.supported_locales', ['en_US']);

        return in_array($locale, $supportedLocales);
    }
}
