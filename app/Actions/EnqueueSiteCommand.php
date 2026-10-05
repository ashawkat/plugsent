<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\SiteCommand;

class EnqueueSiteCommand
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __invoke(
        Site $site,
        string $type,
        ?array $payload = null,
        ?string $batchId = null,
        ?string $source = null,
        ?string $actor = null,
    ): SiteCommand {
        return SiteCommand::query()->create([
            'site_id' => $site->getKey(),
            'batch_id' => $batchId,
            'type' => $type,
            'payload' => $payload,
            'status' => SiteCommand::STATUS_PENDING,
            'expires_at' => now()->addHour(),
            'source' => $source,
            'actor' => $actor,
        ]);
    }
}
