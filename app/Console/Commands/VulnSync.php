<?php

namespace App\Console\Commands;

use App\Actions\StartVulnerabilitySync;
use App\Actions\SyncVulnerabilityFeed;
use Illuminate\Console\Command;
use Throwable;

class VulnSync extends Command
{
    protected $signature = 'vuln:sync';

    protected $description = 'Sync the Wordfence Intelligence vulnerability feed and re-match site inventory';

    public function handle(SyncVulnerabilityFeed $sync, StartVulnerabilitySync $status): int
    {
        try {
            $result = $sync();
        } catch (Throwable $e) {
            $status->put('failed', $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $status->put('success', null, (int) $result['stored']);
        $this->info("{$result['stored']} vulnerability record(s) stored ({$result['removed']} removed). Inventory re-matched.");

        return self::SUCCESS;
    }
}
