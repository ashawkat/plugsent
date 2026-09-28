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

        $rows = array_map(function (array $row): array {
            return [
                'id' => $row['site_id'],
                'name' => $row['site_name'],
                'url' => $row['site_url'],
                'view_url' => SiteResource::getUrl('view', ['record' => $row['site_id']]),
                'score' => $row['score'],
                'vulns' => $row['vulns'],
                'reasons' => $row['reasons'],
            ];
        }, $summary['attention']);

        return [
            'rows' => $rows,
            'sitesUrl' => SiteResource::getUrl('index'),
        ];
    }
}
