<?php

namespace App\Actions;

use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UptimeIncident;
use App\Models\User;
use App\Models\Vulnerability;
use App\Models\Workspace;
use App\Support\CommandSubject;
use Illuminate\Support\Facades\Cache;

/**
 * One pass over a workspace's visible sites producing every number the
 * fleet dashboard, the Sites summary strip, and the daily snapshot need.
 * Cached for a minute so the dashboard's widgets share a single pass.
 *
 * The cached payload must stay scalar: Eloquent models do not survive the
 * cache's unserialize() roundtrip (they come back as
 * __PHP_Incomplete_Class), so site references are ids + names.
 */
class GetFleetSummary
{
    /**
     * Summary for the sites the user may see in the workspace (a null user
     * means the whole workspace, used by the daily snapshot command).
     *
     * @return array{
     *     sites_total: int,
     *     sites_connected: int,
     *     sites_scored: int,
     *     avg_score: ?int,
     *     sites_scoring_80: int,
     *     updates_pending: int,
     *     updates_core_pending: int,
     *     vulns_open: int,
     *     vuln_severities: array{critical: int, high: int, medium: int, low: int, unknown: int},
     *     per_site: array<int, array{updates: int, core_updates: int, vulns: int, vuln_severities: array{critical: int, high: int, medium: int, low: int, unknown: int}}>,
     *     sites_scanned: int,
     *     checks: array<int, array{key: string, label: string, passed: int, total: int}>,
     *     checks_avg_passing: ?float,
     *     uptime_avg: ?float,
     *     uptime_rows: array<int, array{site_id: int, site_name: string, pct: float, days: array<int, array{date: string, downtime_seconds: int, label: string}>}>,
     *     attention: array<int, array{site_id: int, site_name: string, site_url: string, status: string, score: ?int, updates: int, vulns: int, reasons: array<int, string>}},
     *     weakest: ?array{id: int, name: string, score: int},
     *     activity: array<int, array{id: int, subject: string, status: string, site_id: int, site_name: string, when: string}>,
     * }
     */
    public function __invoke(Workspace $workspace, ?User $user = null): array
    {
        return Cache::remember(
            $this->cacheKey($workspace, $user),
            now()->addMinute(),
            fn (): array => $this->compute($workspace, $user),
        );
    }

    public function cacheKey(Workspace $workspace, ?User $user): string
    {
        return 'plugsent.fleet-summary.'.$workspace->getKey().'.'.($user?->getKey() ?? 'all');
    }

    private function compute(Workspace $workspace, ?User $user): array
    {
        $sites = app(ResolveVisibleSites::class)($workspace, $user)
            ->with('inventory')
            ->orderBy('name')
            ->get();

        $inventory = $sites->flatMap->inventory;

        $perSite = [];

        foreach ($sites as $site) {
            $pending = $site->inventory->filter(
                fn (InventoryItem $item): bool => (bool) $item->update_available,
            );

            $perSite[$site->getKey()] = [
                'updates' => $pending->count(),
                'core_updates' => $pending->filter(
                    fn (InventoryItem $item): bool => $item->context === 'core',
                )->count(),
                'vulns' => 0,
                'vuln_severities' => self::emptySeverities(),
            ];
        }

        // --- Vulnerabilities with severity buckets, per site and fleet-wide.
        $severities = self::emptySeverities();
        $affected = $inventory->filter(
            fn (InventoryItem $item): bool => (int) $item->vuln_count > 0 && (string) $item->version !== '',
        );

        if ($affected->isNotEmpty()) {
            $vulns = Vulnerability::query()
                ->whereIn('software_type', $affected->pluck('context')->unique()->values()->all())
                ->whereIn('software_slug', $affected->pluck('slug')->unique()->values()->all())
                ->get();

            foreach ($affected as $item) {
                foreach ($vulns as $vuln) {
                    if ($vuln->software_type !== $item->context
                        || $vuln->software_slug !== $item->slug
                        || ! $vuln->affectsVersion((string) $item->version)) {
                        continue;
                    }

                    $bucket = Vulnerability::severityBucket(
                        $vuln->cvss !== null ? (float) $vuln->cvss : null,
                    );

                    $severities[$bucket]++;
                    $perSite[$item->site_id]['vulns']++;
                    $perSite[$item->site_id]['vuln_severities'][$bucket]++;
                }
            }
        }

        // --- Security checks: aggregate pass counts across scanned sites.
        $evaluator = app(EvaluateSiteSecurity::class);
        $checks = [];
        $passedPerSite = [];
        $failedKeysPerSite = [];

        foreach ($sites as $site) {
            if ($site->security_scanned_at === null) {
                continue;
            }

            $result = $evaluator->evaluate($site);
            $passedPerSite[$site->getKey()] = count(
                array_filter($result['checks'], fn (array $check): bool => $check['passed']),
            );

            foreach ($result['checks'] as $check) {
                $checks[$check['key']] ??= [
                    'key' => $check['key'],
                    'label' => $check['label'],
                    'passed' => 0,
                    'total' => 0,
                ];

                $checks[$check['key']]['total']++;

                if ($check['passed']) {
                    $checks[$check['key']]['passed']++;
                } else {
                    $failedKeysPerSite[$site->getKey()][] = $check['key'];
                }
            }
        }

        // --- 30-day uptime per monitored site, from one incidents query.
        $computer = app(ComputeUptimeRate::class);
        $monitored = $sites->filter(fn (Site $site): bool => $site->uptime_enabled);
        $incidentsBySite = $monitored->isNotEmpty()
            ? UptimeIncident::query()
                ->whereIn('site_id', $monitored->pluck('id'))
                ->where('started_at', '>', now()->subDays(30))
                ->get()
                ->groupBy('site_id')
            : collect();

        $uptimeRows = [];
        $percentages = [];

        foreach ($monitored as $site) {
            $rate = $computer($site, $incidentsBySite->get($site->getKey(), collect()));

            $uptimeRows[] = ['site_id' => $site->getKey(), 'site_name' => $site->name] + $rate;
            $percentages[] = $rate['pct'];
        }

        // --- Sites that need attention, worst first, with reasons.
        $attention = $sites
            ->map(fn (Site $site): array => [
                'site_id' => $site->getKey(),
                'site_name' => $site->name,
                'site_url' => $site->url,
                'status' => $site->status,
                'score' => $site->security_score,
                'updates' => $perSite[$site->getKey()]['updates'],
                'vulns' => $perSite[$site->getKey()]['vulns'],
                'reasons' => $this->attentionReasons($site, $perSite[$site->getKey()], $failedKeysPerSite[$site->getKey()] ?? []),
            ])
            ->filter(fn (array $row): bool => $row['reasons'] !== [])
            ->sort(function (array $a, array $b): int {
                $aDown = $a['status'] !== 'connected';
                $bDown = $b['status'] !== 'connected';

                if ($aDown !== $bDown) {
                    return $aDown ? -1 : 1;
                }

                return ($a['score'] ?? 101) <=> ($b['score'] ?? 101);
            })
            ->values()
            ->take(8)
            ->all();

        $scored = $sites->filter(fn (Site $site): bool => $site->security_score !== null);
        $weakestSite = $scored->isNotEmpty() ? $scored->sortBy('security_score')->first() : null;
        $subjectFormatter = app(CommandSubject::class);

        return [
            'sites_total' => $sites->count(),
            'sites_connected' => $sites->filter(fn (Site $site): bool => $site->isConnected())->count(),
            'sites_scored' => $scored->count(),
            'avg_score' => $scored->isNotEmpty() ? (int) round($scored->avg('security_score')) : null,
            'sites_scoring_80' => $scored->where('security_score', '>=', 80)->count(),
            'updates_pending' => array_sum(array_column($perSite, 'updates')),
            'updates_core_pending' => array_sum(array_column($perSite, 'core_updates')),
            'vulns_open' => array_sum($severities),
            'vuln_severities' => $severities,
            'per_site' => $perSite,
            'sites_scanned' => count($passedPerSite),
            'checks' => array_values($checks),
            'checks_avg_passing' => $passedPerSite !== []
                ? round(array_sum($passedPerSite) / count($passedPerSite), 1)
                : null,
            'uptime_avg' => $percentages !== []
                ? round(array_sum($percentages) / count($percentages), 2)
                : null,
            'uptime_rows' => $uptimeRows,
            'attention' => $attention,
            'weakest' => $weakestSite !== null
                ? ['id' => $weakestSite->getKey(), 'name' => $weakestSite->name, 'score' => (int) $weakestSite->security_score]
                : null,
            'activity' => SiteCommand::query()
                ->whereIn('site_id', $sites->pluck('id'))
                ->with('site')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(fn (SiteCommand $command): array => [
                    'id' => $command->getKey(),
                    'subject' => $subjectFormatter->format($command),
                    'status' => $command->status,
                    'site_id' => $command->site_id,
                    'site_name' => $command->site?->name ?? '—',
                    'when' => $command->created_at?->diffForHumans() ?? '',
                ])
                ->all(),
        ];
    }

    /**
     * @param  array{updates: int, core_updates: int, vulns: int, vuln_severities: array{critical: int, high: int, medium: int, low: int, unknown: int}}  $perSite
     * @param  array<int, string>  $failedChecks
     * @return array<int, string>
     */
    private function attentionReasons(Site $site, array $perSite, array $failedChecks): array
    {
        $reasons = [];

        if (! $site->isConnected()) {
            $reasons[] = 'Disconnected — last seen '.($site->last_seen_at?->diffForHumans() ?? 'never');
        }

        $severities = $perSite['vuln_severities'];

        if ($severities['critical'] > 0) {
            $reasons[] = $severities['critical'].' critical vulnerabilit'.($severities['critical'] === 1 ? 'y' : 'ies');
        } elseif ($severities['high'] > 0) {
            $reasons[] = $severities['high'].' high-severit'.($severities['high'] === 1 ? 'y' : 'ies');
        }

        if (in_array('core_updated', $failedChecks, true)) {
            $reasons[] = 'WordPress core behind';
        }

        if (in_array('php_supported', $failedChecks, true)) {
            $reasons[] = 'PHP '.$site->php_version.' unsupported';
        }

        if ($site->ssl_expires_at !== null && $site->ssl_expires_at->lte(now()->addDays(14))) {
            $reasons[] = 'SSL expires '.$site->ssl_expires_at->format('M j');
        }

        if ($site->domain_expires_at !== null && $site->domain_expires_at->lte(now()->addDays(14))) {
            $reasons[] = 'Domain expires '.$site->domain_expires_at->format('M j');
        }

        if ($reasons === [] && $site->security_score !== null && $site->security_score < 50) {
            $reasons[] = 'Security score '.$site->security_score.'/100';
        }

        return $reasons;
    }

    /**
     * @return array{critical: int, high: int, medium: int, low: int, unknown: int}
     */
    private static function emptySeverities(): array
    {
        return ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'unknown' => 0];
    }
}
