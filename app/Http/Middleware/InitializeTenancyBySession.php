<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyBySession
{
    protected ?string $baseCachePrefix = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->captureBaseCachePrefix();

        $tenant = $this->resolveTenantFromSession($request);

        if ($tenant) {
            $this->initializeTenancy($tenant);
            $this->setCachePrefixForTenant($tenant->getTenantKey());
        } else {
            $this->clearTenantContext();
        }

        try {
            return $next($request);
        } finally {
            if ($tenant) {
                $this->clearTenantContext();
            }
        }
    }

    protected function resolveTenantFromSession(Request $request): ?Tenant
    {
        $tenantId = $request->session()->get('tenant_id');

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $request->session()->forget('tenant_id');
            Log::warning('Session contained tenant_id that does not exist.', ['tenant_id' => $tenantId]);

            return null;
        }

        return $tenant;
    }

    protected function initializeTenancy(Tenant $tenant): void
    {
        $current = tenancy()->tenant;

        if (! tenancy()->initialized || ! $current || $current->getTenantKey() !== $tenant->getTenantKey()) {
            tenancy()->initialize($tenant);
        }
    }

    protected function clearTenantContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->resetCachePrefix();
    }

    protected function setCachePrefixForTenant(?string $tenantKey): void
    {
        if (! $tenantKey) {
            return;
        }

        config(['cache.prefix' => sprintf('%s%s-', $this->baseCachePrefix, $tenantKey)]);
    }

    protected function resetCachePrefix(): void
    {
        if ($this->baseCachePrefix !== null) {
            config(['cache.prefix' => $this->baseCachePrefix]);
        }
    }

    protected function captureBaseCachePrefix(): void
    {
        if ($this->baseCachePrefix === null) {
            $this->baseCachePrefix = config('cache.prefix');
        }
    }
}
