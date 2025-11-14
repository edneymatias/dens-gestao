<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tenant Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('db_name')
                    ->label('Database Name')
                    ->required()
                    ->maxLength(191)
                    ->helperText('Unique DB identifier for the tenant (used to provision DB).')
                    ->scopedUnique(\App\Models\Tenant::class, 'db_name'),

                Select::make('provision_state')
                    ->label('Provision State')
                    ->options([
                        'pending' => 'pending',
                        'provisioning' => 'provisioning',
                        'provisioned' => 'provisioned',
                        'failed' => 'failed',
                    ])
                    ->default('pending')
                    ->disabled(),

                KeyValue::make('data')
                    ->label('Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Optional extra attributes stored as JSON.'),
            ]);
    }
}
