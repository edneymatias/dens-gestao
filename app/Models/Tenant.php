<?php

namespace App\Models;

use App\Concerns\HasAppDefaults;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends StanclTenant implements TenantWithDatabase
{
    use HasAppDefaults;

    // Central connection (landlord)
    protected $connection = 'central';

    protected $fillable = [
        'id',
        'name',
        'db_name',
        'data',
        'provision_state',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Ensure Stancl's virtual column system keeps explicit columns on the model table.
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'db_name',
            'provision_state',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    /**
     * Return a DatabaseConfig instance for this tenant.
     */
    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this);
    }

    protected static function booted(): void
    {
        static::deleting(function ($tenant) {
            // Only remove the database when this is a permanent deletion (forceDelete)
            // When soft-deleting, isForceDeleting() returns false.
            if (method_exists($tenant, 'isForceDeleting') && $tenant->isForceDeleting()) {
                try {
                    // Ensure that db_name/credentials are present
                    $tenant->database()->makeCredentials();

                    // Deletion via Stancl's manager (uses configured template connection)
                    $tenant->database()->manager()->deleteDatabase($tenant);
                } catch (\Throwable $e) {
                    // Do not stop model deletion if the DB is not removed; just log the error.
                    Log::error(sprintf('Error removing tenant DB %s: %s', $tenant->getTenantKey(), $e->getMessage()));
                }
            }
        });
    }
}
