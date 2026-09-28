<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use App\Filament\Resources\Sites\SiteResource;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The sites that need work first — disconnected or failing checks —
 * each with the reason(s) it made the list.
 */
class NeedsAttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $uptimeBySite = collect($summary['uptime_rows'])->keyBy(fn (array $row): int => $row['site']->getKey());

        $rows = array_map(function (array $row) use ($summary, $uptimeBySite): array {
            $site = $row['site'];
            $perSite = $summary['per_site'][$site->getKey()] ?? ['updates' => 0, 'vulns' => 0];

            return [
                'site' => $site,
                'url' => SiteResource::getUrl('view', ['record' => $site->getKey()]),
                'score' => $site->security_score,
                'updates' => $perSite['updates'],
                'vulns' => $perSite['vulns'],
                'uptime' => $uptimeBySite->get($site->getKey())['pct'] ?? null,
                'reasons' => $row['reasons'],
            ];
        }, $summary['attention']);

        return [
            'rows' => $rows,
            'sitesUrl' => SiteResource::getUrl('index'),
        ];
    }
}
