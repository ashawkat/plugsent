<?php

namespace App\Models;

use Database\Factories\FleetSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One day of fleet-wide numbers per workspace, written by the nightly
 * plugsent:snapshot-fleet command. Powers the dashboard's trend charts
 * and week-over-week deltas.
 */
class FleetSnapshot extends Model
{
    /** @use HasFactory<FleetSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'snapshot_date', 'sites_total', 'sites_connected',
        'avg_security_score', 'updates_pending', 'vulns_open', 'vulns_critical',
        'vulns_high', 'uptime_avg_pct',
    ];

    protected function casts(): array
    {
        return [
            // Y-m-d serialization keeps lookup strings and stored values
            // identical for the daily upsert (SQLite compares them as text).
            'snapshot_date' => 'date:Y-m-d',
            'sites_total' => 'integer',
            'sites_connected' => 'integer',
            'avg_security_score' => 'float',
            'updates_pending' => 'integer',
            'vulns_open' => 'integer',
            'vulns_critical' => 'integer',
            'vulns_high' => 'integer',
            'uptime_avg_pct' => 'float',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The last N daily snapshots for a workspace, oldest first — the
     * series behind the dashboard's sparklines.
     *
     * @return Collection<int, self>
     */
    public static function seriesFor(Workspace $workspace, int $days = 14): Collection
    {
        return static::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date')
            ->get();
    }

    /**
     * The most recent snapshot from at least a week ago, for
     * "vs last week" deltas. Null until a week of history exists.
     */
    public static function weekAgo(Workspace $workspace): ?self
    {
        return static::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('snapshot_date', '<=', now()->subDays(7)->toDateString())
            ->orderByDesc('snapshot_date')
            ->first();
    }
}
