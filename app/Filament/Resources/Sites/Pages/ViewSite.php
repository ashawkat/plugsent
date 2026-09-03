<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class ViewSite extends Page
{
    protected static string $resource = SiteResource::class;

    public Site $site;

    protected string $view = 'filament.resources.sites.view-site';

    public function mount(Site $record): void
    {
        Gate::authorize('view', $record);

        $this->site = $record;
    }

    public function getTitle(): string
    {
        return $this->site->name;
    }

    public function refreshInventory(): void
    {
        Gate::authorize('update', $this->site);

        app(EnqueueSiteCommand::class)($this->site, 'inventory.get');

        Notification::make()
            ->title('Inventory refresh queued')
            ->body("{$this->site->name} will report fresh inventory on its next check-in.")
            ->success()
            ->send();
    }

    public function requestUpdate(string $context, string $slug): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected()) {
            Notification::make()
                ->title('Site is not connected')
                ->body('Pair the site first — updates can only run on connected sites.')
                ->danger()
                ->send();

            return;
        }

        app(EnqueueSiteCommand::class)(
            $this->site,
            'update.run',
            ['context' => $context, 'slug' => $slug],
        );

        Notification::make()
            ->title(ucfirst($context).' update queued')
            ->body("\"{$slug}\" will update on the site's next check-in (within a minute), and the inventory will refresh right after.")
            ->success()
            ->send();
    }
}
