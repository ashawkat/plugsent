<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\SiteCommand;

class EnqueueSiteCommand
{
    public function __invoke(Site $site, string $type, ?array $payload = null): SiteCommand
    {
        return SiteCommand::query()->create([
            'site_id' => $site->getKey(),
            'type' => $type,
            'payload' => $payload,
            'status' => SiteCommand::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);
    }
}
