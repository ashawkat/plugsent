<?php

namespace App\Console\Commands;

use App\Actions\GetFleetSummary;
use App\Models\FleetSnapshot;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plugsent:snapshot-fleet')]
#[Description('Record today\'s fleet numbers for every workspace (dashboard trend charts)')]
class SnapshotFleetStats extends Command
{
    public function handle(): int
    {
        $workspaces = Workspace::query()->orderBy('id')->pluck('id');

        foreach ($workspaces as $workspaceId) {
            $workspace = Workspace::query()->findOrFail($workspaceId);

            $summary = app(GetFleetSummary::class)($workspace);

            FleetSnapshot::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->getKey(),
                    'snapshot_date' => now()->toDateString(),
                ],
                [
                    'sites_total' => $summary['sites_total'],
                    'sites_connected' => $summary['sites_connected'],
                    'avg_security_score' => $summary['avg_score'],
                    'updates_pending' => $summary['updates_pending'],
                    'vulns_open' => $summary['vulns_open'],
                    'vulns_critical' => $summary['vuln_severities']['critical'],
                    'vulns_high' => $summary['vuln_severities']['high'],
                    'uptime_avg_pct' => $summary['uptime_avg'],
                ],
            );
        }

        $this->info($workspaces->count().' workspace snapshot(s) recorded.');

        return self::SUCCESS;
    }
}
