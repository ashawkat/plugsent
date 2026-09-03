<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\SiteCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $site = $request->attributes->get('connector.site');

        $site->forceFill([
            'wp_version' => $request->input('wp_version') ?? $site->wp_version,
            'php_version' => $request->input('php_version') ?? $site->php_version,
            'capabilities' => $request->input('capabilities') ?? $site->capabilities,
        ]);
        $site->markSeen();

        $commands = SiteCommand::query()
            ->where('site_id', $site->getKey())
            ->where('status', SiteCommand::STATUS_PENDING)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->orderBy('id')
            ->limit(10)
            ->get();

        SiteCommand::query()
            ->whereIn('id', $commands->modelKeys())
            ->update(['status' => SiteCommand::STATUS_DISPATCHED, 'dispatched_at' => now()]);

        return response()->json([
            'commands' => $commands->map(fn (SiteCommand $command): array => [
                'id' => $command->getKey(),
                'type' => $command->type,
                'payload' => $command->payload,
                'expires_at' => $command->expires_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
