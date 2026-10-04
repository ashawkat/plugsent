<?php

namespace App\Mcp\Tools;

use App\Actions\ComputeUptimeRate;
use App\Actions\EvaluateSiteSecurity;
use App\Mcp\Support\UserSites;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\UptimeIncident;
use App\Models\Vulnerability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Full status of one WordPress site: connection, security score with failing checks, pending updates, open vulnerabilities, uptime, and SSL/domain expiry.')]
class GetSiteStatus extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->required()
                ->description('The site id from list_sites.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $sites = app(UserSites::class)($request->user());
        $site = $sites->firstWhere('id', (int) $request->get('site_id'));

        if ($site === null) {
            return Response::text('No site with that id is visible to this account. Use list_sites to see the available sites.');
        }

        $sections = [
            $this->overview($site),
            $this->security($site),
            $this->updates($site),
            $this->vulnerabilities($site),
            $this->uptime($site),
            $this->expiry($site),
        ];

        return Response::text(
            implode("\n\n", array_filter($sections)),
        );
    }

    private function overview(Site $site): string
    {
        $facts = array_filter([
            $site->connector_version !== null ? 'connector '.$site->connector_version : null,
            $site->wp_version !== null ? 'WordPress '.$site->wp_version : null,
            $site->php_version !== null ? 'PHP '.$site->php_version : null,
        ]);

        $lines = [
            sprintf(
                '%s (%s)%s',
                $site->name,
                $site->url,
                $site->project !== null ? ' — project: '.$site->project->name : '',
            ),
            sprintf(
                'Status: %s, last seen %s%s',
                $site->isConnected() ? 'connected' : ($site->status ?? 'disconnected'),
                $site->last_seen_at?->diffForHumans() ?? 'never',
                $facts !== [] ? ' · '.implode(', ', $facts) : '',
            ),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function updateCounts(Site $site): array
    {
        $pending = $site->inventory->filter(
            fn (InventoryItem $item): bool => (bool) $item->update_available,
        );

        if ($pending->isEmpty()) {
            return [];
        }

        $byContext = $pending->countBy('context');

        return $byContext
            ->map(fn (int $count, string $context): string => "{$count} {$context}")
            ->values()
            ->all();
    }

    private function security(Site $site): string
    {
        if ($site->security_scanned_at === null) {
            return 'Security: not scanned yet — run a security scan from the site\'s dashboard page.';
        }

        $lines = [sprintf('Security score: %d/100 (last scan %s)', $site->security_score ?? 0, $site->security_scanned_at->diffForHumans())];

        $checks = app(EvaluateSiteSecurity::class)->evaluate($site)['checks'];
        $failing = array_filter($checks, fn (array $check): bool => ! $check['passed']);

        if ($failing === []) {
            $lines[] = sprintf('All %d checks pass.', count($checks));
        } else {
            $lines[] = 'Failing checks:';

            foreach ($failing as $check) {
                $lines[] = sprintf('- %s: %s%s', $check['label'], $check['detail'], $check['fix'] !== null ? ' (fix: '.$check['fix'].')' : '');
            }
        }

        return implode("\n", $lines);
    }

    private function updates(Site $site): string
    {
        $counts = $this->updateCounts($site);

        if ($counts === []) {
            return 'Updates: everything is up to date.';
        }

        return sprintf(
            'Updates: %s pending — use list_pending_updates (site_id %d) for details, or update_site to apply them.',
            implode(', ', $counts),
            $site->getKey(),
        );
    }

    private function vulnerabilities(Site $site): string
    {
        $affected = $site->inventory->filter(
            fn (InventoryItem $item): bool => (int) $item->vuln_count > 0 && filled($item->version),
        );

        if ($affected->isEmpty()) {
            return 'Vulnerabilities: none affecting the installed software.';
        }

        $feed = Vulnerability::query()
            ->whereIn('software_type', $affected->pluck('context')->unique()->values()->all())
            ->whereIn('software_slug', $affected->pluck('slug')->unique()->values()->all())
            ->get();

        $lines = ['Known vulnerabilities affecting this site:'];

        foreach ($affected as $item) {
            $feed
                ->filter(fn (Vulnerability $vulnerability): bool => $vulnerability->software_type === $item->context
                    && $vulnerability->software_slug === $item->slug
                    && $vulnerability->affectsVersion((string) $item->version))
                ->each(function (Vulnerability $vulnerability) use ($item, &$lines): void {
                    $cvss = $vulnerability->cvss !== null ? number_format((float) $vulnerability->cvss, 1) : null;
                    $severity = Vulnerability::severityBucket($vulnerability->cvss !== null ? (float) $vulnerability->cvss : null);

                    $lines[] = sprintf(
                        '- %s %s (%s): %s — %s%s%s',
                        $item->name,
                        $item->version,
                        $item->context,
                        $vulnerability->title,
                        $severity,
                        $cvss !== null ? ' CVSS '.$cvss : '',
                        $vulnerability->patched_version !== null ? ' — fixed in '.$vulnerability->patched_version : '',
                    );
                });
        }

        return implode("\n", $lines);
    }

    private function uptime(Site $site): string
    {
        if (! $site->uptime_enabled) {
            return 'Uptime: monitoring is off for this site.';
        }

        $incidents = UptimeIncident::query()
            ->where('site_id', $site->getKey())
            ->where('started_at', '>', now()->subDays(30))
            ->get();

        $rate = app(ComputeUptimeRate::class)($site, $incidents);

        $lines = [sprintf(
            'Uptime: %s, %.2f%% over the last 30 days, last checked %s',
            $site->uptime_status ?? 'unknown',
            (float) $rate['pct'],
            $site->uptime_last_checked_at?->diffForHumans() ?? 'never',
        )];

        if ($site->uptime_last_error !== null) {
            $lines[] = 'Last error: '.$site->uptime_last_error;
        }

        return implode("\n", $lines);
    }

    private function expiry(Site $site): string
    {
        $lines = [];

        foreach (
            [
                'SSL certificate' => $site->ssl_expires_at,
                'Domain' => $site->domain_expires_at,
            ] as $label => $expiresAt
        ) {
            if ($expiresAt === null) {
                continue;
            }

            $days = (int) now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);

            $lines[] = sprintf('%s: expires %s (%d days)', $label, $expiresAt->format('Y-m-d'), $days);
        }

        return $lines === [] ? '' : implode("\n", $lines);
    }
}
