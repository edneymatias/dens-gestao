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
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        // Resolve the canonical supported locale (e.g. convert 'es-ES' or 'es' to
        // the configured supported locale such as 'es' or 'pt_BR'). If no
        // supported locale can be found, fall back to the application default.
        $canonical = $this->findSupportedLocale($locale);

        if ($canonical) {
            App::setLocale($canonical);
        } else {
            App::setLocale(config('app.locale', 'en'));
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

        // Priority 2: authenticated user locale
        $user = Auth::user();

        if ($user && ! empty($user->locale)) {
            return $user->locale;
        }

        // Priority 3: browser locale
        if ($browserLocale = $this->getBrowserLocale($request)) {
            return $browserLocale;
        }

        // No locale resolved
        return null;
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

        // Parse Accept-Language header and extract the locales in order of preference
        // Format: "en-US,en;q=0.9,pt-BR;q=0.8"
        $locales = explode(',', $acceptLanguage);

        foreach ($locales as $locale) {
            // Remove quality factor if present (e.g., ";q=0.9")
            $locale = trim(explode(';', $locale)[0]);

            // Normalize: convert hyphen to underscore and lowercase
            $normalized = str_replace('-', '_', strtolower($locale));

            // Try to find a canonical supported locale
            if ($matched = $this->findSupportedLocale($normalized)) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * Try to find a tenant-specific locale from the central tenant_user table.
     */
    protected function getTenantUserLocale(Request $request): ?string
    {
        // Attempt to find a tenant_user override for the authenticated user.
        // Some test setups initialize tenancy before the request middleware runs,
        // while others may not. To be robust, find any central tenant_user row
        // for the current user if present.
        $userId = Auth::id();

        if (! $userId) {
            return null;
        }

        $query = DB::connection('central')->table('tenant_user')
            ->where('user_id', $userId);

        // If tenancy is initialized and a tenant is available, prefer that tenant
        try {
            $tenant = tenancy()->tenant();
        } catch (\Throwable $e) {
            $tenant = null;
        }

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $row = $query->orderBy('created_at', 'desc')->first();

        return $row->locale ?? null;
    }

    /**
     * Check if a locale is supported.
     */
    protected function isSupportedLocale(string $locale): bool
    {
        return $this->findSupportedLocale($locale) !== null;
    }

    /**
     * Find the canonical supported locale for a given locale string.
     *
     * Returns the configured supported locale (the canonical value from
     * config('app.supported_locales')) when a match is found. Matching is
     * performed case-insensitively and accepts both full locale codes
     * (e.g. en_US, es_ES) and language-only codes (e.g. en, es).
     */
    protected function findSupportedLocale(?string $locale): ?string
    {
        if (! $locale) {
            return null;
        }

        $normalized = str_replace('-', '_', strtolower($locale));

        $supportedLocales = config('app.supported_locales', ['en']);

        // First try exact (normalized) matches against supported locales.
        foreach ($supportedLocales as $supported) {
            $normSupported = str_replace('-', '_', strtolower((string) $supported));

            if ($normalized === $normSupported) {
                // Return the canonical supported locale as configured.
                return (string) $supported;
            }
        }

        // If no exact match, try matching only the language part. Prefer the
        // first supported locale that starts with the language code.
        $lang = explode('_', $normalized)[0];

        foreach ($supportedLocales as $supported) {
            $normSupported = str_replace('-', '_', strtolower((string) $supported));

            if (str_starts_with($normSupported, $lang.'_') || $normSupported === $lang) {
                return (string) $supported;
            }
        }

        return null;
    }
}
