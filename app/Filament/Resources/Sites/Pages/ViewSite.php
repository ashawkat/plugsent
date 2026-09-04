<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateExclusion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ViewSite extends Page
{
    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.sites.view-site';

    /**
     * Remote management commands the page can dispatch. Destructive ones
     * require the site's "delete" ability, not just "update".
     */
    private const MANAGE_ACTION_TYPES = [
        'plugin.activate',
        'plugin.deactivate',
        'plugin.delete',
        'theme.activate',
        'theme.delete',
    ];

    private const DESTRUCTIVE_ACTION_TYPES = [
        'plugin.delete',
        'theme.delete',
    ];

    /**
     * Status-cell labels per action type and command status.
     *
     * @var array<string, array{queued: string, progress: string, done: string, failed: string}>
     */
    private const ACTION_LABELS = [
        'plugin.activate' => [
            'queued' => 'Pending activation…',
            'progress' => 'Activating…',
            'done' => 'Activated ✓',
            'failed' => 'Activation failed',
        ],
        'plugin.deactivate' => [
            'queued' => 'Pending deactivation…',
            'progress' => 'Deactivating…',
            'done' => 'Deactivated ✓',
            'failed' => 'Deactivation failed',
        ],
        'plugin.delete' => [
            'queued' => 'Pending deletion…',
            'progress' => 'Deleting…',
            'done' => 'Deleted ✓',
            'failed' => 'Delete failed',
        ],
        'theme.activate' => [
            'queued' => 'Pending switch…',
            'progress' => 'Switching theme…',
            'done' => 'Switched ✓',
            'failed' => 'Theme switch failed',
        ],
        'theme.delete' => [
            'queued' => 'Pending deletion…',
            'progress' => 'Deleting…',
            'done' => 'Deleted ✓',
            'failed' => 'Delete failed',
        ],
    ];

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
            Action::make('apiKey')
                ->label('API Key')
                ->icon('heroicon-o-key')
                ->modalHeading('API key for the WordPress plugin')
                ->modalDescription('Paste this key into the Plugsent Connector plugin on the site to pair it — no expiry, no one-time code needed.')
                ->modalContent(function (): View {
                    return view(
                        'filament.resources.sites.api-key',
                        ['key' => $this->site->ensureApiKey()],
                    );
                }),
            Action::make('regenerateApiKey')
                ->label('Regenerate key')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This clears the current API key. Any plugin still holding it will no longer be able to pair. A fresh key is generated next time you open the API Key action.')
                ->action(function (): void {
                    $this->site->forceFill(['api_key' => null, 'api_key_hash' => null])->save();

                    Notification::make()
                        ->title('API key cleared')
                        ->success()
                        ->send();
                }),
            Action::make('openWpAdmin')
                ->label('Open wp-admin')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => SiteResource::getUrl('admin-login', ['record' => $this->site->getKey()]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->site->isConnected()),
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

    public function getInventoryFor(string $context): Collection
    {
        return $this->site->inventory()->where('context', $context)->orderBy('name')->get();
    }

    public function pendingCountFor(string $context): int
    {
        return $this->getInventoryFor($context)->where('update_available', true)->count();
    }

    public function updateCategory(string $context): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected()) {
            return;
        }

        $excluded = $this->excludedKeys();
        $all = $this->getInventoryFor($context)->where('update_available', true);
        $items = $all->reject(
            fn (InventoryItem $item): bool => in_array($item->context.'|'.$item->slug, $excluded, true),
        );
        $skipped = $all->count() - $items->count();

        if ($items->isEmpty()) {
            Notification::make()
                ->title('Nothing to update')
                ->body($skipped > 0
                    ? "Every available {$context} update is excluded from updates."
                    : "No {$context} updates are available.")
                ->warning()
                ->send();

            return;
        }

        $batchId = (string) Str::uuid();

        foreach ($items as $item) {
            app(EnqueueSiteCommand::class)(
                $this->site,
                'update.run',
                ['context' => $item->context, 'slug' => $item->slug],
                $batchId,
            );
        }

        Notification::make()
            ->title($items->count().' '.strtolower($context).' updates queued')
            ->body(trim(($skipped > 0 ? $skipped.' excluded item(s) skipped. ' : '')
                .'They run one at a time on the site — live progress below.'))
            ->success()
            ->send();
    }

    /**
     * Queue a remote management command (activate/deactivate/delete/switch).
     * One command per batch, so a fresh inventory follows its completion.
     */
    public function requestAction(string $type, string $slug): void
    {
        if (! in_array($type, self::MANAGE_ACTION_TYPES, true)) {
            return;
        }

        Gate::authorize(
            in_array($type, self::DESTRUCTIVE_ACTION_TYPES, true) ? 'delete' : 'update',
            $this->site,
        );

        if (! $this->site->isConnected() || ! $this->site->supportsCommand($type)) {
            return;
        }

        app(EnqueueSiteCommand::class)($this->site, $type, [
            'context' => Str::before($type, '.'),
            'slug' => $slug,
        ], (string) Str::uuid());

        $verbs = [
            'plugin.activate' => 'Activation of',
            'plugin.deactivate' => 'Deactivation of',
            'plugin.delete' => 'Deletion of',
            'theme.activate' => 'Theme switch to',
            'theme.delete' => 'Deletion of theme',
        ];

        Notification::make()
            ->title('Action queued')
            ->body(($verbs[$type] ?? 'Action on')." \"{$slug}\" starts within seconds"
                .(in_array($type, self::DESTRUCTIVE_ACTION_TYPES, true) ? ' — this cannot be undone.' : ' — watch the status column.'))
            ->success()
            ->send();
    }

    public function toggleUpdateExclusion(string $context, string $slug): void
    {
        Gate::authorize('update', $this->site);

        $existing = UpdateExclusion::query()
            ->where('site_id', $this->site->getKey())
            ->where('context', $context)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            Notification::make()
                ->title('Included in updates')
                ->body("\"{$slug}\" is eligible for updates again.")
                ->success()
                ->send();

            return;
        }

        UpdateExclusion::query()->create([
            'site_id' => $this->site->getKey(),
            'context' => $context,
            'slug' => $slug,
            'created_at' => now(),
        ]);

        Notification::make()
            ->title('Excluded from updates')
            ->body("\"{$slug}\" will no longer appear in update queues.")
            ->success()
            ->send();
    }

    /**
     * Slugs excluded from updates, keyed `context|slug`.
     *
     * @return array<int, string>
     */
    public function excludedKeys(): array
    {
        return $this->site->updateExclusions()
            ->get()
            ->map(fn (UpdateExclusion $exclusion): string => $exclusion->context.'|'.$exclusion->slug)
            ->all();
    }

    public function requestUpdate(string $context, string $slug): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected()) {
            return;
        }

        app(EnqueueSiteCommand::class)(
            $this->site,
            'update.run',
            ['context' => $context, 'slug' => $slug],
            (string) Str::uuid(),
        );

        Notification::make()
            ->title('Update queued')
            ->body("\"{$slug}\" will start within seconds — watch the status column.")
            ->success()
            ->send();
    }

    /**
     * Latest update/management command state per context|slug (last 30
     * minutes). keyBy keeps the newest command when one slug was hit twice.
     *
     * @return array<string, SiteCommand>
     */
    public function commandStates(): array
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', ...self::MANAGE_ACTION_TYPES])
            ->where('created_at', '>', now()->subMinutes(30))
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteCommand $command): bool => is_array($command->payload)
                && isset($command->payload['context'], $command->payload['slug']))
            ->keyBy(function (SiteCommand $command): string {
                $payload = $command->payload ?? [];

                return ($payload['context'] ?? '?').'|'.($payload['slug'] ?? '?');
            })
            ->all();
    }

    public function statusFor(InventoryItem $record): ?string
    {
        $command = $this->commandStates()[$record->context.'|'.$record->slug] ?? null;

        if ($command === null || $command->created_at->lt(now()->subMinutes(30))) {
            return null;
        }

        $labels = self::ACTION_LABELS[$command->type] ?? null;

        if ($labels !== null) {
            return match ($command->status) {
                SiteCommand::STATUS_PENDING => $labels['queued'],
                SiteCommand::STATUS_DISPATCHED => $labels['progress'],
                SiteCommand::STATUS_COMPLETED => $labels['done'],
                SiteCommand::STATUS_FAILED => $labels['failed'],
                default => null,
            };
        }

        return match ($command->status) {
            SiteCommand::STATUS_PENDING => 'Pending update…',
            SiteCommand::STATUS_DISPATCHED => 'Updating…',
            SiteCommand::STATUS_COMPLETED => 'Updated ✓',
            SiteCommand::STATUS_FAILED => 'Update failed',
            default => null,
        };
    }

    /**
     * Whether an update/management command for this item is still in flight
     * (queued or running). Terminal results (done ✓/failed) keep their
     * status text but must not block new actions on the same item.
     */
    public function inFlightFor(InventoryItem $record): bool
    {
        $command = $this->commandStates()[$record->context.'|'.$record->slug] ?? null;

        return $command !== null
            && $command->created_at->gt(now()->subMinutes(30))
            && in_array($command->status, [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED], true);
    }

    public function runningProcesses(): Collection
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', 'inventory.get', ...self::MANAGE_ACTION_TYPES])
            ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subMinutes(10))
            ->orderBy('id')
            ->get();
    }

    /**
     * Human description of an in-flight command for the progress widget.
     */
    public function processSubject(SiteCommand $command): string
    {
        $slug = (string) ($command->payload['slug'] ?? '');

        return match ($command->type) {
            'update.run' => 'Updating · '.$slug,
            'inventory.get' => 'Refreshing inventory',
            'plugin.activate' => 'Activating · '.$slug,
            'plugin.deactivate' => 'Deactivating · '.$slug,
            'plugin.delete' => 'Deleting plugin · '.$slug,
            'theme.activate' => 'Switching theme · '.$slug,
            'theme.delete' => 'Deleting theme · '.$slug,
            default => $command->type,
        };
    }
}
