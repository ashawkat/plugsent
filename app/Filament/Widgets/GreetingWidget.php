<?php

namespace App\Filament\Widgets;

use App\Actions\EnqueueSiteCommand;
use App\Actions\GetFleetSummary;
use App\Actions\ResolveVisibleSites;
use App\Filament\Pages\ConnectSite;
use App\Models\Site;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class GreetingWidget extends Widget
{
    protected string $view = 'filament.widgets.greeting';

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        $attention = count($summary['attention']);
        $disconnected = $summary['sites_total'] - $summary['sites_connected'];

        $sub = $summary['sites_total'].' '.str('site')->plural($summary['sites_total'])
            .' · '.$summary['sites_connected'].' connected';

        if ($attention > 0) {
            $sub .= ' · '.$attention.' need'.($attention === 1 ? 's' : '').' attention';
        }

        if ($disconnected > 0) {
            $sub .= ' · '.$disconnected.' disconnected';
        }

        return [
            'greeting' => $greeting,
            'name' => auth()->user()?->name ?? '',
            'sub' => $sub,
            'connectUrl' => ConnectSite::getUrl(),
            'canRefreshAll' => app(ResolveVisibleSites::class)(Filament::getTenant(), auth()->user())
                ->get()
                ->contains(fn (Site $site): bool => $site->isConnected() && auth()->user()?->can('update', $site)),
        ];
    }

    /**
     * Queue an inventory refresh for every connected site the user may
     * update — the dashboard's "check everything" button.
     */
    public function refreshAll(): void
    {
        $workspace = Filament::getTenant();
        $user = auth()->user();

        $queued = 0;

        foreach (app(ResolveVisibleSites::class)($workspace, $user)->get() as $site) {
            if ($site->isConnected() && $user?->can('update', $site)) {
                app(EnqueueSiteCommand::class)($site, 'inventory.get');

                $queued++;
            }
        }

        if ($queued === 0) {
            Notification::make()
                ->title('Nothing to refresh')
                ->body('No connected sites are available to you right now.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($queued.' inventory refresh'.($queued === 1 ? '' : 'es').' queued')
            ->body('Connected sites report fresh inventory on their next check-in.')
            ->success()
            ->send();
    }
}
