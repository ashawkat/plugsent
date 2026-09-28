<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use App\Models\FleetSnapshot;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The fleet's average security score as a ring, with the quick context
 * stats from the mockup: sites at 80+, average checks passing, weakest
 * site, and the week-over-week delta once snapshots exist.
 */
class FleetHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.fleet-health';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $workspace = Filament::getTenant();

        $summary = app(GetFleetSummary::class)($workspace, auth()->user());
        $weekAgo = FleetSnapshot::weekAgo($workspace);

        $score = $summary['avg_score'];

        $delta = null;

        if ($score !== null && $weekAgo?->avg_security_score !== null) {
            $delta = (int) round($score - $weekAgo->avg_security_score);
        }

        return [
            'summary' => $summary,
            'score' => $score,
            'delta' => $delta,
            // SVG ring: r=62 → circumference 389.6.
            'dasharray' => $score !== null ? (string) round($score / 100 * 389.6, 1) : '0',
            'tone' => $score === null ? 'unknown' : ($score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor')),
        ];
    }
}
