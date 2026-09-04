<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
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

        $batchId = (string) Str::uuid();
        $items = $this->getInventoryFor($context)->where('update_available', true);

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
            ->body('They run one at a time on the site — live progress below.')
            ->success()
            ->send();
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
     * Latest update.run command state per context|slug (last 30 minutes).
     *
     * @return array<string, SiteCommand>
     */
    public function updateCommandStates(): array
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'update.run')
            ->where('created_at', '>', now()->subMinutes(30))
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteCommand $command): bool => is_array($command->payload)
                && isset($command->payload['context'], $command->payload['slug']))
            ->keyBy(fn (SiteCommand $command): string => $command->payload['context'].'|'.$command->payload['slug'])
            ->all();
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

    public function runningProcesses(): Collection
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', 'inventory.get'])
            ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subMinutes(10))
            ->orderBy('id')
            ->get();
    }
}
