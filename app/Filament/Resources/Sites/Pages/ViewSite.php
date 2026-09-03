<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
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
use Illuminate\Support\Str;

class ViewSite extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.sites.view-site';

    public Site $site;

    protected ?array $updateStatesCache = null;

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
            Action::make('updateAll')
                ->label(fn (): string => 'Update all ('.$this->site->pendingUpdatesCount().')')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Updates run one at a time on the site and the inventory refreshes automatically when the batch finishes. Watch the live progress panel below.')
                ->visible(
                    fn (): bool => $this->site->isConnected() && $this->site->pendingUpdatesCount() > 0,
                )
                ->action(function (): void {
                    $batchId = (string) Str::uuid();
                    $items = $this->site->inventory()
                        ->where('update_available', true)
                        ->where('context', '!=', InventoryItem::CONTEXT_CORE)
                        ->orderBy('id')
                        ->get();

                    foreach ($items as $item) {
                        app(EnqueueSiteCommand::class)(
                            $this->site,
                            'update.run',
                            ['context' => $item->context, 'slug' => $item->slug],
                            $batchId,
                        );
                    }

                    Notification::make()
                        ->title($items->count().' updates queued')
                        ->body('The site will pick up the whole batch within seconds and run it step by step — live progress below.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * The most recent batch of activity (updates + trailing inventory
     * refresh) from the last 10 minutes, with progress numbers.
     */
    public function currentBatch(): ?array
    {
        $latest = SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', 'inventory.get'])
            ->where('created_at', '>', now()->subMinutes(10))
            ->orderBy('id', 'desc')
            ->first();

        if (! $latest) {
            return null;
        }

        $commands = $latest->batch_id
            ? SiteCommand::query()
                ->where('batch_id', $latest->batch_id)
                ->whereIn('type', ['update.run', 'inventory.get'])
                ->orderBy('id')
                ->get()
            : collect([$latest]);

        $updates = $commands->where('type', 'update.run');
        $done = $updates->whereIn('status', [SiteCommand::STATUS_COMPLETED, SiteCommand::STATUS_FAILED])->count();
        $total = $updates->count();

        return [
            'commands' => $commands,
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done * 100 / $total) : 0,
            'elapsed' => (int) abs(now()->diffInSeconds($commands->first()->created_at)),
            'finished' => ! $commands->contains(fn (SiteCommand $c) => in_array($c->status, [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])),
        ];
    }

    /**
     * Latest update.run command state per context|slug (last 30 minutes).
     *
     * @return array<string, SiteCommand>
     */
    public function updateCommandStates(): array
    {
        if ($this->updateStatesCache !== null) {
            return $this->updateStatesCache;
        }

        $commands = SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'update.run')
            ->where('created_at', '>', now()->subMinutes(30))
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteCommand $command): bool => is_array($command->payload)
                && isset($command->payload['context'], $command->payload['slug']))
            ->keyBy(fn (SiteCommand $command): string => $command->payload['context'].'|'.$command->payload['slug']);

        return $this->updateStatesCache = $commands->all();
    }

    public function updateStatusFor(InventoryItem $record): ?string
    {
        $command = $this->updateCommandStates()[$record->context.'|'.$record->slug] ?? null;

        if ($command === null || $command->created_at->lt(now()->subMinutes(30))) {
            return null;
        }

        return match ($command->status) {
            SiteCommand::STATUS_PENDING => 'Pending update…',
            SiteCommand::STATUS_DISPATCHED => 'Updating…',
            SiteCommand::STATUS_COMPLETED => 'Updated ✓',
            SiteCommand::STATUS_FAILED => 'Update failed',
            default => null,
        };
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
                TextColumn::make('update_status')
                    ->label('Update status')
                    ->badge()
                    ->state(fn (InventoryItem $record): ?string => $this->updateStatusFor($record))
                    ->color(function (?string $state): string {
                        return match (true) {
                            $state === null => 'gray',
                            str_contains($state, 'Updating') => 'warning',
                            str_contains($state, 'Pending') => 'gray',
                            str_contains($state, 'Updated') => 'success',
                            default => 'danger',
                        };
                    }),
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
                        fn (InventoryItem $record): bool => $record->update_available
                            && $this->site->isConnected()
                            && $this->updateStatusFor($record) === null,
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
                            (string) Str::uuid(),
                        );

                        Notification::make()
                            ->title('Update queued')
                            ->body("\"{$record->name}\" will start within seconds — progress appears below.")
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
            EmbeddedTable::make(),
        ]);
    }
}
