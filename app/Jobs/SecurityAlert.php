<?php

namespace App\Jobs;

use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use App\Support\AlertRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * A site's vulnerability exposure grew (matched after an inventory
 * check-in or a feed sync). Throttled: at most one alert email per site
 * per day, no matter how many items changed.
 */
class SecurityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $siteId) {}

    public function handle(): void
    {
        $site = Site::find($this->siteId);

        if ($site === null || ! $site->isConnected()) {
            return;
        }

        if (! Cache::add('security-alert:'.$site->getKey(), true, now()->addDay())) {
            return;
        }

        $items = InventoryItem::query()
            ->where('site_id', $site->getKey())
            ->where('vuln_count', '>', 0)
            ->orderByDesc('vuln_count')
            ->get(['name', 'slug', 'version', 'vuln_count', 'update_available', 'update_version']);

        if ($items->isEmpty()) {
            return;
        }

        $siteUrl = url('/app/'.$site->workspace->slug.'/sites/'.$site->getKey().'?tab=security');

        /** @var User $user */
        foreach (AlertRecipients::for($site, 'security') as $user) {
            $user->notify(new SecurityAlertNotification($site, $items, $siteUrl));
        }
    }
}
