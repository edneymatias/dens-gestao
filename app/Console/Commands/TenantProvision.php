<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionTenantJob;
use App\Services\TenantProvisionService;
use Exception;
use Illuminate\Console\Command;

class TenantProvision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dens:provision-tenant {name} {--id=} {--seed} {--async} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a new tenant: create DB, run tenant migrations and optional seed.';

    protected TenantProvisionService $service;

    public function __construct(TenantProvisionService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $id = $this->option('id');
        $seed = (bool) $this->option('seed');
        $async = (bool) $this->option('async');

        $this->info("Provisioning tenant: {$name}");

        try {
            if ($async) {
                ProvisionTenantJob::dispatch(['name' => $name, 'id' => $id], true, $seed);
                $this->info('Provision job dispatched (async).');
                return 0;
            }

            $tenant = $this->service->provision(['name' => $name, 'id' => $id], true, $seed);

            $this->info('Tenant provisioned: ' . $tenant->getTenantKey());
            $this->info('DB name: ' . $tenant->database()->getName());

            return 0;
        } catch (Exception $e) {
            $this->error('Provision failed: ' . $e->getMessage());
            return 1;
        }
    }
}
