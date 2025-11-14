<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getHeading(): ?string
    {
        $currentTenant = tenancy()->tenant;

        $tenantLabel = $currentTenant ? filament()->getTenantName($currentTenant) : __('Central');

        return __('Tenants').($tenantLabel ? ' — '.$tenantLabel : '');
    }
}
