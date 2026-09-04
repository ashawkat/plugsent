<?php

namespace App\Http\Controllers\Connector;

use App\Actions\EnqueueSiteCommand;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $site = $request->attributes->get('connector.site');

        $site->forceFill([
            'wp_version' => $request->input('wp_version') ?? $site->wp_version,
            'php_version' => $request->input('php_version') ?? $site->php_version,
            'capabilities' => $request->input('capabilities') ?? $site->capabilities,
            'connector_version' => $request->input('version') ?? $site->connector_version,
        ]);
        $site->markSeen();

        $this->maybeSelfHealInventory($site);

        // Long-polling is OPT-IN: connectors newer than 0.5.0 send `wait`
        // (their HTTP timeout is 45s). Older connectors abort at 15s, so
        // for them we answer immediately, exactly like the pre-long-poll
        // protocol.
        $clientWait = (int) $request->input('wait', 0);
        $seconds = max(0, min($clientWait, (int) config('plugsent.long_poll_seconds', 25), 30));
        $waitUntil = now()->addSeconds($seconds);

        do {
            $commands = $this->pendingCommands($site);

            if ($commands->isNotEmpty() || now()->gte($waitUntil)) {
                break;
            }

            sleep(1);

            // Keep last_seen accurate through the long wait.
            $site->markSeen();
        } while (true);

        if ($commands->isNotEmpty()) {
            SiteCommand::query()
                ->whereIn('id', $commands->modelKeys())
                ->update(['status' => SiteCommand::STATUS_DISPATCHED, 'dispatched_at' => now()]);
        }

        return response()->json([
            'commands' => $commands->map(fn (SiteCommand $command): array => [
                'id' => $command->getKey(),
                'type' => $command->type,
                'payload' => $command->payload,
                'expires_at' => $command->expires_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    protected function pendingCommands(Site $site): Collection
    {
        // Expire stale commands so they never linger as pending.
        SiteCommand::query()
            ->where('site_id', $site->getKey())
            ->where('status', SiteCommand::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => SiteCommand::STATUS_FAILED]);

        return SiteCommand::query()
            ->where('site_id', $site->getKey())
            ->where('status', SiteCommand::STATUS_PENDING)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    protected function maybeSelfHealInventory(Site $site): void
    {
        // A connected site that has never delivered inventory (or lost it)
        // gets asked again — but only when no inventory request is
        // outstanding, throttled to one ask every 5 minutes.
        if ($site->inventory()->count() === 0
            && ! SiteCommand::query()
                ->where('site_id', $site->getKey())
                ->where('type', 'inventory.get')
                ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
                ->exists()
            && ! SiteCommand::query()
                ->where('site_id', $site->getKey())
                ->where('type', 'inventory.get')
                ->where('created_at', '>', now()->subMinutes(5))
                ->exists()) {
            app(EnqueueSiteCommand::class)($site, 'inventory.get');
        }
    }
}
