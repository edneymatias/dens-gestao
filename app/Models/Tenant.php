<?php

namespace App\Models;

use App\Concerns\HasAppDefaults;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\Log;
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
     * Return a DatabaseConfig instance for this tenant.
     */
    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this);
    }

    protected static function booted(): void
    {
        static::deleting(function ($tenant) {
            // Só executar remoção do DB quando for uma exclusão permanente (forceDelete)
            // Quando soft-deleting, isForceDeleting() retorna false.
            if (method_exists($tenant, 'isForceDeleting') && $tenant->isForceDeleting()) {
                try {
                    // Garante que db_name/credentials estejam presentes
                    $tenant->database()->makeCredentials();

                    // Deleção via manager do stancl (usa template connection configurada)
                    $tenant->database()->manager()->deleteDatabase($tenant);
                } catch (\Throwable $e) {
                    // Não pare a remoção do model se o DB não for removido; apenas logue.
                    Log::error(sprintf('Erro ao remover DB do tenant %s: %s', $tenant->getTenantKey(), $e->getMessage()));
                }
            }
        });
    }
}
