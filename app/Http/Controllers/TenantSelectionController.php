<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class TenantSelectionController extends Controller
{
    /**
     * Return the list of tenants the authenticated user has access to.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tenants = $user->tenants()->get(['id', 'name']);

        return response()->json(['tenants' => $tenants]);
    }

    /**
     * Select a tenant for the current session.
     */
    public function select(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
        ]);

        $tenantId = $data['tenant_id'];

        $user = $request->user();

        // Allow Super‑Admin to select any tenant, otherwise ensure membership
        if (! ($user->is_superadmin ?? false)) {
            $has = $user->tenants()->where('tenants.id', $tenantId)->exists();

            if (! $has) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Set tenant in session and regenerate to avoid fixation.
        $request->session()->put('tenant_id', $tenantId);
        $request->session()->regenerate();

        // Fire an event for observers (e.g., audit, notifications)
        try {
            event(new \App\Events\TenantSwitched($user, $tenantId));
        } catch (\Throwable $e) {
            Log::warning('Could not dispatch TenantSwitched event: '.$e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }
}
