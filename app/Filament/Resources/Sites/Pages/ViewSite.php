<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\EnqueueSiteCommand;
use App\Actions\CheckDomainSsl;
use App\Actions\EvaluateSiteSecurity;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateExclusion;
use App\Models\UpdateRun;
use App\Models\Vulnerability;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class ViewSite extends Page
{
    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.sites.view-site';

    /**
     * Remote management commands the page can dispatch. Destructive ones
     * require the site's "delete" ability, not just "update".
     */
    private const MANAGE_ACTION_TYPES = [
        'plugin.activate',
        'plugin.deactivate',
        'plugin.delete',
        'theme.activate',
        'theme.delete',
    ];

    private const DESTRUCTIVE_ACTION_TYPES = [
        'plugin.delete',
        'theme.delete',
    ];

    /**
     * The active section tab. Kept in the URL so views are shareable and
     * survive reloads.
     */
    #[Url]
    public string $tab = 'overview';

    private const TABS = ['overview', 'plugins', 'themes', 'core', 'uptime', 'security', 'history'];

    /**
     * The inventory item whose vulnerability list is shown in the modal,
     * as [context, slug, name, update_available]. Null = modal closed.
     *
     * @var array{context: string, slug: string, name: string, update_available: bool}|null
     */
    public ?array $vulnDetail = null;

    /**
     * Status-cell labels per action type and command status.
     *
     * @var array<string, array{queued: string, progress: string, done: string, failed: string}>
     */
    private const ACTION_LABELS = [
        'update.safe' => [
            'queued' => 'Preparing restore point…',
            'progress' => 'Safe updating…',
            'done' => 'Updated ✓',
            'failed' => 'Safe update failed',
        ],
        'restore.apply' => [
            'queued' => 'Pending restore…',
            'progress' => 'Restoring…',
            'done' => 'Restored ✓',
            'failed' => 'Restore failed',
        ],
        'plugin.activate' => [
            'queued' => 'Pending activation…',
            'progress' => 'Activating…',
            'done' => 'Activated ✓',
            'failed' => 'Activation failed',
        ],
        'plugin.deactivate' => [
            'queued' => 'Pending deactivation…',
            'progress' => 'Deactivating…',
            'done' => 'Deactivated ✓',
            'failed' => 'Deactivation failed',
        ],
        'plugin.delete' => [
            'queued' => 'Pending deletion…',
            'progress' => 'Deleting…',
            'done' => 'Deleted ✓',
            'failed' => 'Delete failed',
        ],
        'theme.activate' => [
            'queued' => 'Pending switch…',
            'progress' => 'Switching theme…',
            'done' => 'Switched ✓',
            'failed' => 'Theme switch failed',
        ],
        'theme.delete' => [
            'queued' => 'Pending deletion…',
            'progress' => 'Deleting…',
            'done' => 'Deleted ✓',
            'failed' => 'Delete failed',
        ],
    ];

    public Site $site;

    public function mount(Site $record, ?string $tab = null): void
    {
        Gate::authorize('view', $record);

        $this->site = $record;

        if ($tab !== null && in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }

        $this->maybeAutoScan();
    }

    public function switchTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;

            if ($tab === 'uptime') {
                $this->refreshDomainSsl();
            }
        }
    }

    /**
     * Refresh domain/SSL expiry when the Uptime tab is opened and the
     * cached lookup is older than a day (both lookups are slow, so they
     * are never run during page polling).
     */
    public function refreshDomainSsl(): void
    {
        if (! $this->site->domain_checked_at?->gt(now()->subDay())) {
            try {
                app(CheckDomainSsl::class)($this->site);
                $this->site->refresh();
            } catch (\Throwable) {
                // Expiry lookups are best-effort; the cards show — when unknown.
            }
        }
    }

    /**
     * 30-day uptime rate and per-day status derived from the recorded
     * incidents: the site counts as up except while an incident is open.
     *
     * @return array{pct: float, days: array<int, array{date: string, downtime_seconds: int, label: string}>}
     */
    public function uptimeRate(): array
    {
        $since = now()->subDays(29)->startOfDay();
        $windowStart = $since->getTimestamp();
        $windowSeconds = max(1, now()->getTimestamp() - $windowStart);

        $incidents = $this->site->uptimeIncidents()
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

    /**
     * Recent commands for this site, newest first — the History tab.
     */
    public function history(): Collection
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    public function getTitle(): string
    {
        return $this->site->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apiKey')
                ->label('API Key')
                ->icon('heroicon-o-key')
                ->modalHeading('API key for the WordPress plugin')
                ->modalDescription('Paste this key into the Plugsent Connector plugin on the site to pair it — no expiry, no one-time code needed.')
                ->modalContent(function (): View {
                    return view(
                        'filament.resources.sites.api-key',
                        ['key' => $this->site->ensureApiKey()],
                    );
                }),
            Action::make('regenerateApiKey')
                ->label('Regenerate key')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This clears the current API key. Any plugin still holding it will no longer be able to pair. A fresh key is generated next time you open the API Key action.')
                ->action(function (): void {
                    $this->site->forceFill(['api_key' => null, 'api_key_hash' => null])->save();

                    Notification::make()
                        ->title('API key cleared')
                        ->success()
                        ->send();
                }),
            Action::make('openWpAdmin')
                ->label('Open wp-admin')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => SiteResource::getUrl('admin-login', ['record' => $this->site->getKey()]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->site->isConnected()),
            Action::make('refreshInventory')
                ->label('Refresh inventory')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->site->isConnected())
                ->action(function (): void {
                    app(EnqueueSiteCommand::class)($this->site, 'inventory.get');

                    Notification::make()
                        ->title('Inventory refresh queued')
                        ->body("{$this->site->name} will report fresh inventory on its next check-in.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getInventoryFor(string $context): Collection
    {
        return $this->site->inventory()->where('context', $context)->orderBy('name')->get();
    }

    public function pendingCountFor(string $context): int
    {
        return $this->getInventoryFor($context)->where('update_available', true)->count();
    }

    public function updateCategory(string $context): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected()) {
            return;
        }

        $excluded = $this->excludedKeys();
        $all = $this->getInventoryFor($context)->where('update_available', true);
        $items = $all->reject(
            fn (InventoryItem $item): bool => in_array($item->context.'|'.$item->slug, $excluded, true),
        );
        $skipped = $all->count() - $items->count();

        if ($items->isEmpty()) {
            Notification::make()
                ->title('Nothing to update')
                ->body($skipped > 0
                    ? "Every available {$context} update is excluded from updates."
                    : "No {$context} updates are available.")
                ->warning()
                ->send();

            return;
        }

        $batchId = (string) Str::uuid();

        foreach ($items as $item) {
            app(EnqueueSiteCommand::class)(
                $this->site,
                $this->updateTypeFor($item->context),
                ['context' => $item->context, 'slug' => $item->slug],
                $batchId,
            );
        }

        Notification::make()
            ->title($items->count().' '.strtolower($context).' updates queued')
            ->body(trim(($skipped > 0 ? $skipped.' excluded item(s) skipped. ' : '')
                .'They run one at a time on the site — restore point, smoke test, and automatic rollback included.'))
            ->success()
            ->send();
    }

    /**
     * Safe by default: plugins and themes go through the safe-update
     * pipeline whenever the site's connector supports it. Core and old
     * connectors keep the plain update.
     */
    private function updateTypeFor(string $context): string
    {
        return $context !== 'core' && $this->site->supportsCommand('update.safe')
            ? 'update.safe'
            : 'update.run';
    }

    /**
     * Queue a remote management command (activate/deactivate/delete/switch).
     * One command per batch, so a fresh inventory follows its completion.
     */
    public function requestAction(string $type, string $slug): void
    {
        if (! in_array($type, self::MANAGE_ACTION_TYPES, true)) {
            return;
        }

        Gate::authorize(
            in_array($type, self::DESTRUCTIVE_ACTION_TYPES, true) ? 'delete' : 'update',
            $this->site,
        );

        if (! $this->site->isConnected() || ! $this->site->supportsCommand($type)) {
            return;
        }

        app(EnqueueSiteCommand::class)($this->site, $type, [
            'context' => Str::before($type, '.'),
            'slug' => $slug,
        ], (string) Str::uuid());

        $verbs = [
            'plugin.activate' => 'Activation of',
            'plugin.deactivate' => 'Deactivation of',
            'plugin.delete' => 'Deletion of',
            'theme.activate' => 'Theme switch to',
            'theme.delete' => 'Deletion of theme',
        ];

        Notification::make()
            ->title('Action queued')
            ->body(($verbs[$type] ?? 'Action on')." \"{$slug}\" starts within seconds"
                .(in_array($type, self::DESTRUCTIVE_ACTION_TYPES, true) ? ' — this cannot be undone.' : ' — watch the status column.'))
            ->success()
            ->send();
    }

    public function toggleUpdateExclusion(string $context, string $slug): void
    {
        Gate::authorize('update', $this->site);

        $existing = UpdateExclusion::query()
            ->where('site_id', $this->site->getKey())
            ->where('context', $context)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            Notification::make()
                ->title('Included in updates')
                ->body("\"{$slug}\" is eligible for updates again.")
                ->success()
                ->send();

            return;
        }

        UpdateExclusion::query()->create([
            'site_id' => $this->site->getKey(),
            'context' => $context,
            'slug' => $slug,
            'created_at' => now(),
        ]);

        Notification::make()
            ->title('Excluded from updates')
            ->body("\"{$slug}\" will no longer appear in update queues.")
            ->success()
            ->send();
    }

    /**
     * Slugs excluded from updates, keyed `context|slug`.
     *
     * @return array<int, string>
     */
    public function excludedKeys(): array
    {
        return $this->site->updateExclusions()
            ->get()
            ->map(fn (UpdateExclusion $exclusion): string => $exclusion->context.'|'.$exclusion->slug)
            ->all();
    }

    public function requestUpdate(string $context, string $slug): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected()) {
            return;
        }

        $type = $this->updateTypeFor($context);

        app(EnqueueSiteCommand::class)(
            $this->site,
            $type,
            ['context' => $context, 'slug' => $slug],
            (string) Str::uuid(),
        );

        Notification::make()
            ->title('Update queued')
            ->body($type === 'update.safe'
                ? "\"{$slug}\" will be updated with a restore point, smoke test, and automatic rollback — starting within seconds."
                : "\"{$slug}\" will start within seconds — watch the status column.")
            ->success()
            ->send();
    }

    /**
     * Manually restore an item from its newest restore point (files +
     * database). Site-level delete permission: it rewrites site state.
     */
    public function requestRestore(string $context, string $slug): void
    {
        Gate::authorize('delete', $this->site);

        if (! $this->site->isConnected() || ! $this->site->supportsCommand('restore.apply')) {
            return;
        }

        app(EnqueueSiteCommand::class)(
            $this->site,
            'restore.apply',
            ['context' => $context, 'slug' => $slug],
            (string) Str::uuid(),
        );

        Notification::make()
            ->title('Restore queued')
            ->body("\"{$slug}\" will be restored to its backed-up version (files and database) within seconds.")
            ->success()
            ->send();
    }

    /**
     * Whether the current user may trigger a restore (site-delete level:
     * it rewrites files and the database on the site).
     */
    public function canRestore(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('delete', $this->site);
    }

    /**
     * Items that still hold a usable file restore point, keyed `context|slug`
     * (the newest run wins).
     *
     * @return array<int, string>
     */
    public function restorableKeys(): array
    {
        return $this->site->updateRuns()
            ->where('status', UpdateRun::STATUS_UPDATED)
            ->where('files_backup', true)
            ->orderBy('id')
            ->get()
            ->keyBy(fn (UpdateRun $run): string => $run->context.'|'.$run->slug)
            ->keys()
            ->all();
    }

    public function toggleUptime(): void
    {
        Gate::authorize('update', $this->site);

        $this->site->forceFill(['uptime_enabled' => ! $this->site->uptime_enabled])->save();

        Notification::make()
            ->title($this->site->uptime_enabled ? 'Uptime monitoring on' : 'Uptime monitoring off')
            ->body($this->site->uptime_enabled
                ? "{$this->site->name} is checked every ".config('plugsent.uptime_interval_minutes', 5).' minutes.'
                : "Checks for {$this->site->name} are paused. Existing incidents stay on record.")
            ->success()
            ->send();
    }

    /**
     * Recent downtime episodes, newest first.
     */
    public function recentIncidents(): Collection
    {
        return $this->site->uptimeIncidents()
            ->orderByDesc('id')
            ->limit(12)
            ->get();
    }

    /**
     * Whether the site's connector can run security scans and hardening.
     */
    public function securitySupported(): bool
    {
        return $this->site->supportsCommand('security.scan')
            && $this->site->supportsCommand('security.harden');
    }

    /**
     * The checks + score derived from the last scan's facts.
     *
     * @return array{checks: array<int, array{key: string, label: string, passed: bool, detail: string, fix: ?string}>, score: int}
     */
    public function securityEvaluation(): array
    {
        return app(EvaluateSiteSecurity::class)->evaluate($this->site);
    }

    /**
     * Whether a security scan is currently queued or running.
     */
    public function securityScanInFlight(): bool
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'security.scan')
            ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subMinutes(10))
            ->exists();
    }

    /**
     * Whether a hardening toggle is currently being applied.
     */
    public function hardeningInFlight(string $key, bool $enable): bool
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'security.harden')
            ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subMinutes(10))
            ->get()
            ->contains(fn (SiteCommand $command) => ($command->payload['key'] ?? null) === $key
                && (bool) ($command->payload['enable'] ?? false) === $enable);
    }

    public function runSecurityScan(): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected() || ! $this->securitySupported()) {
            return;
        }

        app(EnqueueSiteCommand::class)($this->site, 'security.scan');

        Notification::make()
            ->title('Security scan queued')
            ->body("{$this->site->name} will report its security facts on its next check-in.")
            ->success()
            ->send();
    }

    /**
     * Apply or remove one hardening protection on the site. A fresh scan
     * follows automatically (ResultsController), so the score updates.
     */
    public function requestHardening(string $key, bool $enable): void
    {
        Gate::authorize('update', $this->site);

        if (! $this->site->isConnected() || ! $this->securitySupported()) {
            return;
        }

        app(EnqueueSiteCommand::class)($this->site, 'security.harden', ['key' => $key, 'enable' => $enable]);

        Notification::make()
            ->title($enable ? 'Hardening queued' : 'Reverting protection queued')
            ->body("The change applies on the site's next check-in; the security score refreshes right after.")
            ->success()
            ->send();
    }

    /**
     * Sites running connector 0.13.0+ get a scan automatically when the
     * last one is missing or stale, so the section is never empty.
     */
    private function maybeAutoScan(): void
    {
        if (! $this->site->isConnected() || ! $this->securitySupported()) {
            return;
        }

        if ($this->securityScanInFlight()) {
            return;
        }

        $scannedAt = $this->site->security_scanned_at;

        if ($scannedAt !== null && $scannedAt->gt(now()->subHours(12))) {
            return;
        }

        app(EnqueueSiteCommand::class)($this->site, 'security.scan');
    }

    /**
     * Latest update/management command state per context|slug (last 30
     * minutes). keyBy keeps the newest command when one slug was hit twice.
     *
     * @return array<string, SiteCommand>
     */
    public function commandStates(): array
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', 'update.safe', 'restore.apply', ...self::MANAGE_ACTION_TYPES])
            ->where('created_at', '>', now()->subMinutes(30))
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteCommand $command): bool => is_array($command->payload)
                && isset($command->payload['context'], $command->payload['slug']))
            ->keyBy(function (SiteCommand $command): string {
                $payload = $command->payload ?? [];

                return ($payload['context'] ?? '?').'|'.($payload['slug'] ?? '?');
            })
            ->all();
    }

    public function statusFor(InventoryItem $record): ?string
    {
        $command = $this->commandStates()[$record->context.'|'.$record->slug] ?? null;

        if ($command === null || $command->created_at->lt(now()->subMinutes(30))) {
            return null;
        }

        // The safe pipeline's "done" is result-aware: a completed command
        // whose smoke test failed still means the update was rolled back.
        if ($command->type === 'update.safe' && $command->status === SiteCommand::STATUS_COMPLETED) {
            return data_get($command->result, 'data.safe.rolled_back')
                ? 'Rolled back ⚠'
                : 'Updated ✓';
        }

        $labels = self::ACTION_LABELS[$command->type] ?? null;

        if ($labels !== null) {
            return match ($command->status) {
                SiteCommand::STATUS_PENDING => $labels['queued'],
                SiteCommand::STATUS_DISPATCHED => $labels['progress'],
                SiteCommand::STATUS_COMPLETED => $labels['done'],
                SiteCommand::STATUS_FAILED => $labels['failed'],
                default => null,
            };
        }

        return match ($command->status) {
            SiteCommand::STATUS_PENDING => 'Pending update…',
            SiteCommand::STATUS_DISPATCHED => 'Updating…',
            SiteCommand::STATUS_COMPLETED => 'Updated ✓',
            SiteCommand::STATUS_FAILED => 'Update failed',
            default => null,
        };
    }

    /**
     * All known vulnerabilities matching a software slug, most severe first.
     */
    public function vulnerabilitiesFor(string $context, string $slug): Collection
    {
        return Vulnerability::query()
            ->where('software_type', $context)
            ->where('software_slug', $slug)
            ->orderByDesc('cvss')
            ->orderByDesc('published_at')
            ->get();
    }

    public function openVulnerabilities(string $context, string $slug, string $name, bool $updateAvailable): void
    {
        $this->vulnDetail = [
            'context' => $context,
            'slug' => $slug,
            'name' => $name,
            'update_available' => $updateAvailable,
        ];
    }

    public function closeVulnerabilities(): void
    {
        $this->vulnDetail = null;
    }

    /**
     * Severity label/color bucket for a CVSS score.
     */
    public static function cvssBucket(?float $cvss): string
    {
        return match (true) {
            $cvss === null => 'unknown',
            $cvss >= 9.0 => 'critical',
            $cvss >= 7.0 => 'high',
            $cvss >= 4.0 => 'medium',
            default => 'low',
        };
    }

    /**
     * Human-readable affected-version range, e.g. "versions 1.2 – 3.4".
     */
    public static function affectedRangeText(Vulnerability $vulnerability): string
    {
        if ($vulnerability->affected_from === null && $vulnerability->affected_to === null) {
            return 'All versions';
        }

        $from = $vulnerability->affected_from
            ? ($vulnerability->affected_from_inclusive ? '≥ ' : '> ').$vulnerability->affected_from
            : null;
        $to = $vulnerability->affected_to
            ? ($vulnerability->affected_to_inclusive ? '≤ ' : '< ').$vulnerability->affected_to
            : null;

        return collect([$from, $to])->filter()->implode(' and ');
    }

    /**
     * Plain-text description extracted from the raw feed record.
     */
    public static function descriptionFor(Vulnerability $vulnerability, int $limit = 700): ?string
    {
        $description = data_get($vulnerability->raw, 'description')
            ?? data_get($vulnerability->raw, 'software.0.description');

        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        $text = trim(strip_tags($description));

        return $text === '' ? null : Str::limit($text, $limit);
    }

    /**
     * Reference URLs attached to the raw feed record.
     */
    public static function referencesFor(Vulnerability $vulnerability): array
    {
        $references = data_get($vulnerability->raw, 'references');

        if (! is_array($references)) {
            return [];
        }

        return collect($references)
            ->map(fn ($url) => is_string($url) ? $url : (string) ($url['url'] ?? ''))
            ->filter(fn (string $url) => str_starts_with($url, 'http'))
            ->unique()
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * Human-readable summary of known vulnerabilities for an item.
     */
    public function vulnerabilityTitlesFor(InventoryItem $item): ?string
    {
        $titles = Vulnerability::query()
            ->where('software_type', $item->context)
            ->where('software_slug', $item->slug)
            ->orderByDesc('cvss')
            ->limit(3)
            ->pluck('title')
            ->all();

        if ($titles === []) {
            return null;
        }

        return implode(' · ', array_map(fn (string $title) => Str::limit($title, 80), $titles));
    }

    /**
     * Whether an update/management command for this item is still in flight
     * (queued or running). Terminal results (done ✓/failed) keep their
     * status text but must not block new actions on the same item.
     */
    public function inFlightFor(InventoryItem $record): bool
    {
        $command = $this->commandStates()[$record->context.'|'.$record->slug] ?? null;

        return $command !== null
            && $command->created_at->gt(now()->subMinutes(30))
            && in_array($command->status, [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED], true);
    }

    public function runningProcesses(): Collection
    {
        return SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('type', ['update.run', 'update.safe', 'restore.apply', 'inventory.get', ...self::MANAGE_ACTION_TYPES])
            ->whereIn('status', [SiteCommand::STATUS_PENDING, SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subMinutes(10))
            ->orderBy('id')
            ->get();
    }

    /**
     * Human description of an in-flight command for the progress widget.
     */
    public function processSubject(SiteCommand $command): string
    {
        $slug = (string) ($command->payload['slug'] ?? '');

        return match ($command->type) {
            'update.run' => 'Updating · '.$slug,
            'update.safe' => 'Safe updating · '.$slug,
            'restore.apply' => 'Restoring · '.$slug,
            'inventory.get' => 'Refreshing inventory',
            'plugin.activate' => 'Activating · '.$slug,
            'plugin.deactivate' => 'Deactivating · '.$slug,
            'plugin.delete' => 'Deleting plugin · '.$slug,
            'theme.activate' => 'Switching theme · '.$slug,
            'theme.delete' => 'Deleting theme · '.$slug,
            default => $command->type,
        };
    }
}
