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

    public ?string $error = null;

    public bool $enqueued = false;

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

        // Older connectors reject admin.login as unsupported — surface that
        // immediately instead of letting the page spin.
        $caps = $record->capabilities;

        if (is_array($caps) && ! in_array('admin.login', $caps)) {
            $this->error = 'This site is running an outdated connector. Update the Plugsent Connector plugin on the site to 0.7.0 or newer, then retry.';

            return;
        }

        $this->enqueue();
    }

    public function enqueue(): void
    {
        Gate::authorize('update', $this->site);

        app(EnqueueSiteCommand::class)($this->site, 'admin.login');

        $this->error = null;
        $this->enqueued = true;
    }

    public function getTitle(): string
    {
        return $this->error
            ? 'Could not open WordPress admin'
            : 'Connecting to '.$this->site->name;
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

        if (! $command) {
            return;
        }

        if ($command->status === SiteCommand::STATUS_COMPLETED) {
            $url = $command->result['data']['admin_login']['url'] ?? null;

            if (filled($url)) {
                $this->redirect($url);
            }

            return;
        }

        if ($command->status === SiteCommand::STATUS_FAILED) {
            $this->error = 'The site rejected the login request: '
                .($command->result['error'] ?? 'unknown error')
                .' — update the connector on the site to 0.7.0+ and retry.';

            return;
        }

        // No answer within 150s of queueing: treat as unreachable. WP-Cron
        // hosts can take up to a minute to answer, so stay patient.
        if ($command->created_at->lt(now()->subSeconds(150))
            && $command->status !== SiteCommand::STATUS_COMPLETED) {
            $this->error = 'The site did not answer in time. Check that it is online and running connector 0.8.0+, then retry.';
        }
    }
}
