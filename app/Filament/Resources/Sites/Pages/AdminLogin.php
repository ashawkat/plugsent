<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use App\Models\SiteCommand;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class AdminLogin extends Page
{
    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.sites.admin-login';

    public Site $site;

    public function mount(Site $record): void
    {
        Gate::authorize('update', $record);

        if (! $record->isConnected()) {
            Notification::make()
                ->title('Site is not connected')
                ->danger()
                ->send();

            $this->redirect(SiteResource::getUrl('index'));

            return;
        }

        $this->site = $record;

        app(EnqueueSiteCommand::class)($this->site, 'admin.login');
    }

    public function getTitle(): string
    {
        return 'Connecting to '.$this->site->name;
    }

    /**
     * Called by the 2s page poll: once the connector answers with a magic
     * login URL, send the browser straight into wp-admin.
     */
    public function checkForLoginUrl(): void
    {
        $command = SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'admin.login')
            ->orderBy('id', 'desc')
            ->first();

        if (! $command || $command->status !== SiteCommand::STATUS_COMPLETED) {
            return;
        }

        $url = $command->result['data']['admin_login']['url'] ?? null;

        if (filled($url)) {
            $this->redirect($url);
        }
    }
}
