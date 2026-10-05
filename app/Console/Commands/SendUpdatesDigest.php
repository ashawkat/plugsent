<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\UpdatesAvailableNotification;
use App\Support\AppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendUpdatesDigest extends Command
{
    private const SENT_KEY = 'plugsent.updates-digest-sent';

    protected $signature = 'plugsent:updates-digest';

    protected $description = 'Send the once-a-day "updates available" digest per workspace (skips workspaces with nothing pending)';

    /**
     * The digest is the single updates email Plugsent sends — one per day,
     * at a time configured in Settings. The schedule ticks every 15
     * minutes; this guard makes it fire once, after the configured time.
     */
    public function handle(): int
    {
        if (! $this->dueToday()) {
            return self::SUCCESS;
        }

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

        Cache::put(self::SENT_KEY, today()->toDateString(), now()->endOfDay());

        $this->info("Updates digest sent to {$sent} recipient(s).");

        return self::SUCCESS;
    }

    private function dueToday(): bool
    {
        $time = app(AppSettings::class)->get(AppSettings::UPDATES_DIGEST_TIME, '08:00') ?? '08:00';

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '08:00';
        }

        if (now()->format('H:i') < $time) {
            return false;
        }

        return Cache::get(self::SENT_KEY) !== today()->toDateString();
    }

    private function notifyRecipients(Workspace $workspace, array $siteDigest): int
    {
        // Query from the workspace side: wherePivotIn inside whereHas
        // compiles to a bogus constraint and matches nobody.
        $users = $workspace->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get()
            ->filter(fn (User $user) => $user->wantsEmail('updates'));

        foreach ($users as $user) {
            $user->notify(new UpdatesAvailableNotification($workspace, $siteDigest));
        }

        return $users->count();
    }
}
