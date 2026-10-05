<?php

namespace App\Actions;

use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateRun;
use Illuminate\Support\Facades\Log;

/**
 * The connector reports an update as "ok" without checking that the
 * version actually moved — licensed plugins refuse silently and the
 * dashboard then claims an update is applied forever. When fresh
 * inventory lands after an update batch, this judges each recent run
 * against what the site actually reports.
 */
class VerifyUpdateRuns
{
    /**
     * Inventory lands right after an update batch finishes, so runs older
     * than this were judged (or superseded) already.
     */
    private const WINDOW_MINUTES = 90;

    public function __invoke(Site $site): void
    {
        $this->verifySafeRuns($site);
        $this->verifyPlainRuns($site);
    }

    /**
     * Safe-pipeline runs: from_version is recorded, so "still showing the
     * old version" is provable.
     */
    private function verifySafeRuns(Site $site): void
    {
        UpdateRun::query()
            ->where('site_id', $site->getKey())
            ->where('status', UpdateRun::STATUS_UPDATED)
            ->whereNull('verified_at')
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->get()
            ->each(function (UpdateRun $run) use ($site): void {
                $item = InventoryItem::query()
                    ->where('site_id', $site->getKey())
                    ->where('context', $run->context)
                    ->where('slug', $run->slug)
                    ->first();

                if ($item === null || ! $item->update_available) {
                    // Gone, or the site no longer offers an update: took.
                    $run->forceFill(['verified_at' => now()])->save();

                    return;
                }

                if ((string) $item->version !== (string) $run->from_version) {
                    // Still an update pending, but the version moved — took.
                    $run->forceFill(['verified_at' => now()])->save();

                    return;
                }

                $run->forceFill([
                    'status' => UpdateRun::STATUS_FAILED,
                    'verified_at' => now(),
                    'message' => sprintf(
                        'Connector reported success, but %s is still on %s — the update did not take. Check the plugin/theme license or run it from wp-admin.',
                        $item->name,
                        $run->from_version,
                    ),
                ])->save();

                $this->annotateCommand($run->command_id, $run->message);
            });
    }

    /**
     * Plain runs (core updates, connectors without the safe pipeline) keep
     * no from_version — but if the site still offers an update for the
     * exact item minutes after the run, it did not take.
     */
    private function verifyPlainRuns(Site $site): void
    {
        SiteCommand::query()
            ->where('site_id', $site->getKey())
            ->where('type', 'update.run')
            ->where('status', SiteCommand::STATUS_COMPLETED)
            ->whereNull('payload->verified')
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->get()
            ->each(function (SiteCommand $command) use ($site): void {
                $context = $command->payload['context'] ?? null;
                $slug = $command->payload['slug'] ?? null;

                if ($context === null || $slug === null) {
                    $command->forceFill(['payload' => array_merge($command->payload ?? [], ['verified' => true])])->save();

                    return;
                }

                $item = InventoryItem::query()
                    ->where('site_id', $site->getKey())
                    ->where('context', $context)
                    ->where('slug', $slug)
                    ->first();

                if ($item === null || ! $item->update_available) {
                    $command->forceFill(['payload' => array_merge($command->payload ?? [], ['verified' => true])])->save();

                    return;
                }

                $message = sprintf(
                    'Verification: an update is still offered for %s after this run — it may not have applied. Check the site.',
                    $item->name,
                );

                $command->forceFill([
                    'payload' => array_merge($command->payload ?? [], ['verified' => true]),
                    'result' => array_merge($command->result ?? [], [
                        'verification' => ['ok' => false, 'message' => $message],
                    ]),
                ])->save();

                Log::info('Update verification flagged a plain update run.', [
                    'site_id' => $site->getKey(),
                    'command_id' => $command->getKey(),
                    'context' => $context,
                    'slug' => $slug,
                ]);
            });
    }

    private function annotateCommand(?int $commandId, string $message): void
    {
        if ($commandId === null) {
            return;
        }

        $command = SiteCommand::query()->find($commandId);

        if ($command === null) {
            return;
        }

        $command->forceFill([
            'result' => array_merge($command->result ?? [], [
                'verification' => ['ok' => false, 'message' => $message],
            ]),
        ])->save();
    }
}
