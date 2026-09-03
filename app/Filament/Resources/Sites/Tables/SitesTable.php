<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Site $record): string => SiteResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('url')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('project.name')
                    ->label('Project'),
                TextColumn::make('pending_updates')
                    ->label('Updates')
                    ->state(fn (Site $record): int => $record->inventory()->where('update_available', true)->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} pending" : 'Up to date'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_seen_at')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'connected' => 'Connected',
                        'error' => 'Error',
                    ]),
                SelectFilter::make('project')
                    ->relationship(
                        'project',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where(
                            'workspace_id',
                            Filament::getTenant()?->getKey(),
                        ),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refreshInventory')
                    ->label('Refresh inventory')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Site $record): bool => $record->isConnected())
                    ->action(function (Site $record): void {
                        app(EnqueueSiteCommand::class)($record, 'inventory.get');

                        Notification::make()
                            ->title('Inventory refresh queued')
                            ->body("{$record->name} will check in within a minute.")
                            ->success()
                            ->send();
                    }),
                Action::make('revoke')
                    ->label('Revoke access')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Site $record): bool => $record->isConnected())
                    ->action(function (Site $record): void {
                        $record->credential()->update(['status' => 'revoked']);
                        $record->forceFill(['status' => 'pending'])->save();

                        Notification::make()
                            ->title('Connector access revoked')
                            ->body("{$record->name} can no longer check in until it pairs again.")
                            ->warning()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
