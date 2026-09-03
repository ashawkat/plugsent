<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->required()
                    ->url()
                    ->maxLength(255),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'connected' => 'Connected',
                        'error' => 'Error',
                    ])
                    ->default('pending')
                    ->required(),
                self::getProjectSelect(),
            ]);
    }

    protected static function getProjectSelect(): Component
    {
        return Select::make('project_id')
            ->label('Project')
            ->relationship(
                'project',
                'name',
                modifyQueryUsing: fn (Builder $query) => $query->where(
                    'workspace_id',
                    Filament::getTenant()?->getKey(),
                ),
            )
            ->searchable()
            ->preload()
            ->required();
    }
}
