<?php

namespace App\Http\Controllers\Connector;

use App\Actions\EnqueueSiteCommand;
use App\Actions\ProcessInventoryResult;
use App\Http\Controllers\Controller;
use App\Models\SiteCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $site = $request->attributes->get('connector.site');

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.id' => ['required', 'integer'],
            'results.*.status' => ['required', 'in:ok,failed'],
            'results.*.data' => ['nullable', 'array'],
            'results.*.error' => ['nullable', 'string'],
        ]);

        $processed = 0;

        foreach ($validated['results'] as $result) {
            $command = SiteCommand::query()
                ->where('site_id', $site->getKey())
                ->whereKey($result['id'])
                ->first();

            if ($command === null) {
                continue;
            }

            $command->forceFill([
                'status' => $result['status'] === 'ok'
                    ? SiteCommand::STATUS_COMPLETED
                    : SiteCommand::STATUS_FAILED,
                'result' => array_filter([
                    'data' => $result['data'] ?? null,
                    'error' => $result['error'] ?? null,
                ]),
                'completed_at' => now(),
            ])->save();

            if ($command->type === 'inventory.get' && $result['status'] === 'ok') {
                app(ProcessInventoryResult::class)($site, $result['data']['inventory'] ?? []);
            }

            // After any update, queue a fresh inventory so the dashboard
            // reflects the new versions — but only once per batch, when the
            // whole batch has finished (no more pending/dispatched commands).
            if ($command->type === 'update.run') {
                $batchOutstanding = SiteCommand::query()
                    ->where('site_id', $site->getKey())
                    ->where('batch_id', $command->batch_id)
                    ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
                    ->exists();

                if (! $batchOutstanding) {
                    app(EnqueueSiteCommand::class)($site, 'inventory.get');
                }

                // The command ran but the update did not apply (e.g. the theme
                // cache was mid-refresh, transient race). Retry a limited number
                // of times automatically, within the same batch.
                if ($result['status'] === 'ok'
                    && ($result['data']['update']['ok'] ?? null) === false) {
                    $retry = (int) ($command->payload['retry'] ?? 0);

                    if ($retry < 2) {
                        app(EnqueueSiteCommand::class)($site, 'update.run', array_merge(
                            $command->payload ?? [],
                            ['retry' => $retry + 1],
                        ), $command->batch_id);
                    }
                }
            }

            $processed++;
        }

        return response()->json(['processed' => $processed]);
    }
}
