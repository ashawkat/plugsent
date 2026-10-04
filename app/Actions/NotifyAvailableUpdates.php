<?php

namespace App\Actions;

use App\Models\InventoryItem;
use App\Models\Site;
use App\Notifications\UpdateDiscoveryNotification;
use App\Support\AlertRecipients;
use Illuminate\Support\Collection;

/**
 * Decide whether a site's fresh inventory should trigger an "updates
 * available" email. The fingerprint of the pending-update set makes the
 * notification edge-triggered: one email per distinct set of updates, no
 * repeats until something changes, and nothing when the site is clean.
 */
class NotifyAvailableUpdates
{
    public function __invoke(Site $site): void
    {
        $items = $this->pendingItems($site);

        if ($items->isEmpty()) {
            if ($site->updates_fingerprint !== null) {
                $site->forceFill(['updates_fingerprint' => null, 'updates_notified_at' => null])->save();
            }

            return;
        }

        $fingerprint = md5($items->map(fn (InventoryItem $item): string => implode('|', [
            $item->context,
            $item->slug,
            (string) $item->version,
            (string) $item->update_version,
        ]))->implode("\n"));

        if ($site->updates_fingerprint === $fingerprint) {
            return;
        }

        $recipients = AlertRecipients::for($site, 'updates');

        foreach ($recipients as $user) {
            $user->notify(new UpdateDiscoveryNotification($site->workspace, $site, $items));
        }

        $site->forceFill(['updates_fingerprint' => $fingerprint, 'updates_notified_at' => now()])->save();
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    private function pendingItems(Site $site): Collection
    {
        return $site->inventory()
            ->where('update_available', true)
            ->orderBy('context')
            ->orderBy('slug')
            ->get();
    }
}
