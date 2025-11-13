<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TenantProvisionReconcile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dens:reconcile-tenants {--threshold=10 : Minutes after which a provisioning tenant is considered stuck} {--limit=50 : Maximum tenants to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile tenants stuck in provisioning state and attempt to complete provisioning.';

    protected TenantProvisionService $service;

    public function __construct(TenantProvisionService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $limit = (int) $this->option('limit');

        $cutoff = Carbon::now()->subMinutes($threshold);

        $tenants = Tenant::query()
            ->where('provision_state', 'provisioning')
            ->where('updated_at', '<=', $cutoff)
            ->limit($limit)
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No stuck tenants found.');
            return 0;
        }

        foreach ($tenants as $tenant) {
            $this->line('Reconciling tenant: ' . $tenant->getTenantKey());

            try {
                // Dispatch an asynchronous job to avoid long-running scheduler tasks.
                \App\Jobs\ProvisionTenantJob::dispatch(['tenant_id' => $tenant->getTenantKey()], true, false);
                $this->info('Dispatched ProvisionTenantJob for: ' . $tenant->getTenantKey());
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch ProvisionTenantJob for ' . $tenant->getTenantKey() . ': ' . $e->getMessage());
                $this->error('Failed to dispatch ProvisionTenantJob for ' . $tenant->getTenantKey() . ': ' . $e->getMessage());
            }
        }

        return 0;
    }
}
