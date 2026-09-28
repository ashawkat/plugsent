<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use App\Filament\Resources\Sites\SiteResource;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Per-site 30-day uptime strips, worst rate first — the fleet-level
 * version of the site detail page's uptime bars.
 */
class FleetUptimeWidget extends Widget
{
    protected string $view = 'filament.widgets.fleet-uptime';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    private const MAX_ROWS = 8;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $rows = collect($summary['uptime_rows'])
            ->sortBy('pct')
            ->take(self::MAX_ROWS)
            ->map(function (array $row): array {
                $days = array_map(function (array $day): string {
                    if ($day['downtime_seconds'] === 0) {
                        return 'ok';
                    }

                    // Same thresholds as the site uptime rate: >= 95% for the
                    // day counts as degraded, anything lower as an outage.
                    $dayPct = (1 - $day['downtime_seconds'] / 86400) * 100;

                    return $dayPct >= 95 ? 'warn' : 'bad';
                }, $row['days']);

                return [
                    'id' => $row['site_id'],
                    'name' => $row['site_name'],
                    'url' => SiteResource::getUrl('view', ['record' => $row['site_id']]),
                    'pct' => $row['pct'],
                    'days' => $days,
                    'dayLabels' => array_map(fn (array $day): string => $day['label'], $row['days']),
                ];
            })
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'monitored' => count($summary['uptime_rows']),
            'hidden' => max(0, count($summary['uptime_rows']) - self::MAX_ROWS),
            'sitesUrl' => SiteResource::getUrl('index'),
        ];
    }
}
