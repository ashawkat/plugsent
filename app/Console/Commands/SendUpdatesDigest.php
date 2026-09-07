<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\Workspace;
use App\Models\User;
use App\Notifications\UpdatesAvailableNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendUpdatesDigest extends Command
{
    protected $signature = 'plugsent:updates-digest';

    protected $description = 'Send the once-a-day "updates available" digest per workspace (skips workspaces with nothing pending)';

    public function handle(): int
    {
        $workspaces = Workspace::query()->with('sites')->get();
        $sent = 0;

        foreach ($workspaces as $workspace) {
            $siteDigest = [];

            foreach ($workspace->sites as $site) {
                $items = InventoryItem::query()
                    ->where('site_id', $site->getKey())
                    ->where('update_available', true)
                    ->orderBy('context')
                    ->orderBy('name')
                    ->get(['name', 'context', 'version', 'update_version']);

                if ($items->isNotEmpty()) {
                    $siteDigest[] = ['site' => $site, 'items' => $items];
                }
            }

            if ($siteDigest === []) {
                continue;
            }

            $sent += $this->notifyRecipients($workspace, $siteDigest);
        }

        $this->info("Updates digest sent to {$sent} recipient(s).");

        return self::SUCCESS;
    }

    private function notifyRecipients(Workspace $workspace, array $siteDigest): int
    {
        $users = User::query()
            ->whereHas('workspaces', fn ($query) => $query->whereKey($workspace->getKey())->wherePivotIn('role', ['owner', 'admin']))
            ->get()
            ->filter(fn (User $user) => $user->wantsEmail('updates'));

        foreach ($users as $user) {
            $user->notify(new UpdatesAvailableNotification($workspace, new Collection($siteDigest)));
        }

        return $users->count();
    }
}
