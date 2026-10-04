<?php

namespace App\Console\Commands;

use App\Actions\EnqueueSiteCommand;
use App\Models\Site;
use App\Models\SiteCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plugsent:refresh-inventory')]
#[Description('Queue an inventory rescan for every connected site so update lists stay fresh')]
class RefreshInventory extends Command
{
    public function handle(): int
    {
        $queued = 0;
        $skipped = 0;

        foreach (Site::query()->where('status', 'connected')->get() as $site) {
            // An outstanding inventory request means the connector has not
            // answered yet — stacking another one behind it would report
            // stale data twice, so leave it alone.
            $outstanding = SiteCommand::query()
                ->where('site_id', $site->getKey())
                ->where('type', 'inventory.get')
                ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
                ->exists();

            if ($outstanding) {
                $skipped++;

                continue;
            }

            app(EnqueueSiteCommand::class)($site, 'inventory.get');
            $queued++;
        }

        $this->info("Inventory refresh queued for {$queued} site(s)".($skipped > 0 ? ", {$skipped} skipped (scan already outstanding)" : '').'.');

        return self::SUCCESS;
    }
}
