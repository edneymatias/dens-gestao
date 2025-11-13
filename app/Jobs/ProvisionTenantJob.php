<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantProvisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProvisionTenantJob supports two modes:
 * - Provision a new tenant when passed ['name' => ..., 'id' => ...]
 * - Reconcile/complete provisioning for an existing tenant when passed ['tenant_id' => '...']
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public bool $migrate;

    public bool $seed;

    /**
     * @param  mixed  $payload  Array with tenant data (name/id) or ['tenant_id' => '...']
     */
    public function __construct($payload, bool $migrate = true, bool $seed = false)
    {
        $this->payload = $payload;
        $this->migrate = $migrate;
        $this->seed = $seed;
    }

    public function handle(TenantProvisionService $service): void
    {
        try {
            if (is_array($this->payload) && isset($this->payload['tenant_id'])) {
                $tenant = Tenant::find($this->payload['tenant_id']);

                if (! $tenant) {
                    Log::warning('ProvisionTenantJob: tenant not found: '.$this->payload['tenant_id']);

                    return;
                }

                // Use completeProvision to finish provisioning for an existing tenant.
                $service->completeProvision($tenant, $this->migrate, $this->seed);

                return;
            }

            // Default: provision new tenant using provided data array
            $data = is_array($this->payload) ? $this->payload : [];
            $service->provision($data, $this->migrate, $this->seed);
        } catch (Throwable $e) {
            Log::error('Asynchronous tenant provisioning failed: '.$e->getMessage());
            throw $e;
        }
    }
}
