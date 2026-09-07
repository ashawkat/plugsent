<?php

namespace App\Http\Controllers\Connector;

use App\Actions\EnqueueSiteCommand;
use App\Actions\EvaluateSiteSecurity;
use App\Actions\ProcessInventoryResult;
use App\Http\Controllers\Controller;
use App\Models\SiteCommand;
use App\Models\UpdateRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    /**
     * Command types that change plugin/theme state on the site; when the
     * batch holding one finishes, a fresh inventory is queued so the
     * dashboard reflects new versions and active flags.
     */
    private const STATE_CHANGING_TYPES = [
        'update.run',
        'update.safe',
        'restore.apply',
        'plugin.activate',
        'plugin.deactivate',
        'plugin.delete',
        'theme.activate',
        'theme.delete',
    ];

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

            if ($command->type === 'security.scan' && $result['status'] === 'ok') {
                app(EvaluateSiteSecurity::class)($site, $result['data']['security'] ?? []);
            }

            // After a toggle applied on the site, refresh the facts so the
            // score reflects the new state.
            if ($command->type === 'security.harden' && $result['status'] === 'ok') {
                app(EnqueueSiteCommand::class)($site, 'security.scan');
            }

            // Audit-trail the safe pipeline. A refused pipeline already
            // rolled itself back on the site — it is never retried.
            if ($command->type === 'update.safe' && $result['status'] === 'ok') {
                $safe = $result['data']['safe'] ?? [];

                UpdateRun::query()->create([
                    'site_id' => $site->getKey(),
                    'context' => (string) ($safe['context'] ?? ''),
                    'slug' => (string) ($safe['slug'] ?? ''),
                    'command_id' => $command->getKey(),
                    'from_version' => $safe['from_version'] ?? null,
                    'to_version' => $safe['to_version'] ?? null,
                    'status' => ($safe['rolled_back'] ?? false)
                        ? UpdateRun::STATUS_ROLLED_BACK
                        : (($safe['ok'] ?? false) ? UpdateRun::STATUS_UPDATED : UpdateRun::STATUS_FAILED),
                    'message' => $safe['message'] ?? null,
                    'smoke_ok' => $safe['smoke']['ok'] ?? null,
                    'smoke_status_code' => $safe['smoke']['status_code'] ?? null,
                    'db_backup' => (bool) ($safe['db_backup'] ?? false),
                    'files_backup' => (bool) ($safe['files_backup'] ?? false),
                ]);
            }

            // After any state-changing command (update or management action),
            // queue a fresh inventory — but only once per batch, when the
            // whole batch has finished (no more pending/dispatched commands).
            if (in_array($command->type, self::STATE_CHANGING_TYPES, true)) {
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
