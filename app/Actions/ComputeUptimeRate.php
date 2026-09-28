<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\UptimeIncident;
use Illuminate\Support\Collection;

class ComputeUptimeRate
{
    /**
     * 30-day uptime rate and per-day status derived from the recorded
     * incidents: the site counts as up except while an incident is open.
     *
     * Pass a window-filtered incidents collection to avoid one query per
     * site when a whole fleet is evaluated at once.
     *
     * @param  Collection<int, UptimeIncident>|null  $incidents
     * @return array{pct: float, days: array<int, array{date: string, downtime_seconds: int, label: string}>}
     */
    public function __invoke(Site $site, ?Collection $incidents = null): array
    {
        $since = now()->subDays(29)->startOfDay();
        $windowStart = $since->getTimestamp();
        $windowSeconds = max(1, now()->getTimestamp() - $windowStart);

        $incidents ??= $site->uptimeIncidents()
            ->where('started_at', '>', $since->copy()->subDays(2))
            ->where('started_at', '>', now()->subDays(30))
            ->get();

        $downPerDay = array_fill(0, 30, 0);

        foreach ($incidents as $incident) {
            $start = max($incident->started_at->getTimestamp(), $windowStart);
            $end = $incident->ended_at?->getTimestamp() ?? now()->getTimestamp();

            for ($day = 0; $day < 30; $day++) {
                $dayStart = $windowStart + $day * 86400;
                $dayEnd = $dayStart + 86400;

                $overlap = min($end, $dayEnd) - max($start, $dayStart);

                if ($overlap > 0) {
                    $downPerDay[$day] += $overlap;
                }
            }
        }

        $totalDown = array_sum($downPerDay);

        $days = [];

        foreach ($downPerDay as $index => $down) {
            $date = $since->copy()->addDays($index);

            if ($down === 0) {
                $label = $date->format('M j').' — 100%';
            } else {
                $minutes = (int) floor($down / 60);
                $pct = round((1 - $down / 86400) * 100, 2);
                $label = $date->format('M j').' — '.$pct.'% ('.$minutes.'m downtime)';
            }

            $days[] = [
                'date' => $date->format('M j'),
                'downtime_seconds' => $down,
                'label' => $label,
            ];
        }

        return [
            'pct' => round((1 - $totalDown / $windowSeconds) * 100, 2),
            'days' => $days,
        ];
    }
}
