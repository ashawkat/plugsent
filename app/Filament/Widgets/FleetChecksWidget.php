<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The 14 real security checks (EvaluateSiteSecurity) aggregated across
 * the scanned sites — worst pass-rate first, so the fleet's systematic
 * gaps float to the top.
 */
class FleetChecksWidget extends Widget
{
    protected string $view = 'filament.widgets.fleet-checks';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $checks = collect($summary['checks'])
            ->map(function (array $check): array {
                $ratio = $check['total'] > 0 ? $check['passed'] / $check['total'] : 0;

                return $check + [
                    'ratio' => $ratio,
                    'percent' => (int) round($ratio * 100),
                    'tone' => $ratio >= 0.75 ? 'good' : ($ratio >= 0.4 ? 'fair' : 'poor'),
                ];
            })
            ->sortBy('ratio')
            ->values()
            ->all();

        return [
            'checks' => $checks,
            'sitesScanned' => $summary['sites_scanned'],
        ];
    }
}
