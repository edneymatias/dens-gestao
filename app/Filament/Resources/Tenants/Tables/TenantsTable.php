<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Events\TenantSwitched;
use App\Jobs\ProvisionTenantJob;
use Filament\Actions\Action as FilamentAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament as FilamentFacade;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn as TableTextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Tenant Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('db_name')
                    ->label('Database Name')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('provision_state')
                    ->label('Provision State')
                    ->colors([
                        'primary' => 'pending',
                        'warning' => 'provisioning',
                        'success' => 'provisioned',
                        'danger' => 'failed',
                    ])
                    ->sortable(),

                TableTextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                FilamentAction::make('provision')
                    ->label('Provision')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => ($record->provision_state ?? null) !== 'provisioned' && (request()->user()?->is_superadmin ?? false))
                    ->action(function ($record) {
                        // Dispatch asynchronous provisioning job for existing tenant
                        ProvisionTenantJob::dispatch(['tenant_id' => (string) $record->id]);

                        Notification::make()
                            ->title('Provisioning started')
                            ->success()
                            ->send();
                    }),

                FilamentAction::make('switch')
                    ->label('Switch')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // set tenant in session and regenerate
                        session(['tenant_id' => $record->id]);
                        session()->regenerate();

                        try {
                            event(new TenantSwitched(request()->user(), (string) $record->id));
                        } catch (\Throwable $e) {
                            // ignore
                        }

                        // redirect to Filament panel URL (avoid relying on a specific route name)
                        return redirect(FilamentFacade::getUrl());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
