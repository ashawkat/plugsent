<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\InventoryItem;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\HasTabs;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ViewSite extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.sites.view-site';

    public Site $site;

    public function mount(Site $record): void
    {
        Gate::authorize('view', $record);

        $this->site = $record;
    }

    public function getTitle(): string
    {
        return $this->site->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshInventory')
                ->label('Refresh inventory')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->site->isConnected())
                ->action(function (): void {
                    app(EnqueueSiteCommand::class)($this->site, 'inventory.get');

                    Notification::make()
                        ->title('Inventory refresh queued')
                        ->body("{$this->site->name} will report fresh inventory on its next check-in.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryItem::query()->where('site_id', $this->site->getKey()),
            )
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (InventoryItem $record): ?string => $record->slug),
                TextColumn::make('context')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'plugin' => 'Plugin',
                        'theme' => 'Theme',
                        default => 'Core',
                    }),
                TextColumn::make('version')
                    ->label('Installed version'),
                TextColumn::make('update_version')
                    ->label('Available update')
                    ->badge()
                    ->color('success')
                    ->placeholder('—'),
                TextColumn::make('active')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'active' : 'inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('context')
                    ->label('Type')
                    ->options([
                        'plugin' => 'Plugins',
                        'theme' => 'Themes',
                        'core' => 'Core',
                    ]),
                TernaryFilter::make('update_available')
                    ->label('Has update'),
            ])
            ->recordActions([
                Action::make('runUpdate')
                    ->label('Update')
                    ->visible(
                        fn (InventoryItem $record): bool => $record->update_available && $this->site->isConnected(),
                    )
                    ->requiresConfirmation(
                        fn (InventoryItem $record): bool => $record->context === InventoryItem::CONTEXT_CORE,
                    )
                    ->modalDescription(
                        fn (InventoryItem $record): string => $record->context === InventoryItem::CONTEXT_CORE
                            ? 'Update WordPress core on this site? The site will apply it on its next check-in.'
                            : "Update {$record->name} on its next check-in? A fresh inventory follows automatically."
                    )
                    ->action(function (InventoryItem $record): void {
                        Gate::authorize('update', $this->site);

                        app(EnqueueSiteCommand::class)(
                            $this->site,
                            'update.run',
                            ['context' => $record->context, 'slug' => $record->slug],
                        );

                        Notification::make()
                            ->title('Update queued')
                            ->body("\"{$record->name}\" will update on the site's next check-in (within a minute).")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nothing reported yet')
            ->emptyStateDescription('The site sends its inventory on each check-in.');
    }

    public function getTabs(): array
    {
        $base = InventoryItem::query()->where('site_id', $this->site->getKey());

        return [
            null => Tab::make('All')
                ->badge((clone $base)->count()),
            'plugins' => Tab::make('Plugins')
                ->badge((clone $base)->where('context', 'plugin')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('context', 'plugin')),
            'themes' => Tab::make('Themes')
                ->badge((clone $base)->where('context', 'theme')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('context', 'theme')),
            'core' => Tab::make('WordPress core')
                ->badge((clone $base)->where('context', 'core')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('context', 'core')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }
}
