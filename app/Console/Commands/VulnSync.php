<?php

namespace App\Console\Commands;

use App\Actions\SyncVulnerabilityFeed;
use Illuminate\Console\Command;
use Throwable;

class VulnSync extends Command
{
    protected $signature = 'vuln:sync';

    protected $description = 'Sync the Wordfence Intelligence vulnerability feed and re-match site inventory';

    public function handle(SyncVulnerabilityFeed $sync): int
    {
        try {
            $result = $sync();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['stored']} vulnerability record(s) stored ({$result['removed']} removed, {$result['pages']} page(s) fetched). Inventory re-matched.");

        return self::SUCCESS;
    }
}
