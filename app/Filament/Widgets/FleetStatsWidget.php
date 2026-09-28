<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use App\Models\FleetSnapshot;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four fleet KPIs, each with its 14-day snapshot chart and a
 * week-over-week delta in the description.
 */
class FleetStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2];

    protected int|array|null $columns = 2;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $workspace = Filament::getTenant();

        if ($workspace === null) {
            return [];
        }

        $summary = app(GetFleetSummary::class)($workspace, auth()->user());
        $series = FleetSnapshot::seriesFor($workspace);
        $weekAgo = FleetSnapshot::weekAgo($workspace);

        $disconnected = $summary['sites_total'] - $summary['sites_connected'];
        $severities = $summary['vuln_severities'];

        $updatesDelta = $weekAgo !== null
            ? $summary['updates_pending'] - $weekAgo->updates_pending
            : null;
        $vulnsDelta = $weekAgo !== null
            ? $summary['vulns_open'] - $weekAgo->vulns_open
            : null;

        $otherVulns = $severities['medium'] + $severities['low'] + $severities['unknown'];

        return [
            Stat::make('Sites online', $summary['sites_connected'].' / '.$summary['sites_total'])
                ->description($disconnected > 0
                    ? $disconnected.' disconnected · '.count($summary['attention']).' need attention'
                    : 'All sites connected')
                ->descriptionIcon($disconnected > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->descriptionColor($disconnected > 0 ? 'warning' : 'success')
                ->chart($series->map(fn (FleetSnapshot $snapshot): int => $snapshot->sites_connected)->all())
                ->chartColor('success'),

            Stat::make('Pending updates', (string) $summary['updates_pending'])
                ->description($this->deltaText($updatesDelta, lowerIsBetter: true,
                    fallback: 'across '.count(array_filter($summary['per_site'], fn (array $site): bool => $site['updates'] > 0))
                        .' sites'.($summary['updates_core_pending'] > 0
                            ? ' · '.$summary['updates_core_pending'].' WordPress core'
                            : '')))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->descriptionColor($this->deltaColor($updatesDelta, lowerIsBetter: true))
                ->chart($series->map(fn (FleetSnapshot $snapshot): int => $snapshot->updates_pending)->all())
                ->chartColor('primary'),

            Stat::make('Open vulnerabilities', (string) $summary['vulns_open'])
                ->description($this->deltaText($vulnsDelta, lowerIsBetter: true,
                    fallback: ($severities['critical'].' critical · '.$severities['high'].' high · '.$otherVulns.' other')))
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->descriptionColor($summary['vulns_open'] > 0 ? 'danger' : 'success')
                ->chart($series->map(fn (FleetSnapshot $snapshot): int => $snapshot->vulns_open)->all())
                ->chartColor('danger'),

            Stat::make('Uptime · 30 days', $summary['uptime_avg'] !== null ? $summary['uptime_avg'].'%' : '—')
                ->description(count($summary['uptime_rows']).' '.str('site')->plural(count($summary['uptime_rows'])).' monitored')
                ->descriptionIcon('heroicon-m-signal')
                ->descriptionColor('success')
                ->chart($series->map(fn (FleetSnapshot $snapshot): ?float => $snapshot->uptime_avg_pct)->all())
                ->chartColor('success'),
        ];
    }

    private function deltaText(?int $delta, bool $lowerIsBetter, string $fallback): string
    {
        if ($delta === null || $delta === 0) {
            return $fallback;
        }

        $arrow = $delta > 0 ? '▲' : '▼';

        return $arrow.abs($delta).' vs last week · '.$fallback;
    }

    private function deltaColor(?int $delta, bool $lowerIsBetter): string
    {
        if ($delta === null || $delta === 0) {
            return 'gray';
        }

        return ($lowerIsBetter ? $delta < 0 : $delta > 0) ? 'success' : 'danger';
    }
}
