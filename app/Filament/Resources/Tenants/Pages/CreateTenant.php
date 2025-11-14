<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Jobs\ProvisionTenantJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        // If the creator is a superadmin, kick off asynchronous provisioning immediately.
        if (request()->user()?->is_superadmin ?? false) {
            ProvisionTenantJob::dispatch(['tenant_id' => (string) $this->record->id]);

            Notification::make()
                ->title('Provisioning started')
                ->success()
                ->send();
        }
    }
}
