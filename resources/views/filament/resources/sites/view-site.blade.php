<x-filament-panels::page wire:poll.3s>
    @php
        $connected = $this->site->isConnected();
        $running = $this->runningProcesses();
        $excluded = $this->excludedKeys();
        $restorableKeys = $this->restorableKeys();
        $tabs = [
            'overview' => 'Overview',
            'plugins' => 'Plugins',
            'themes' => 'Themes',
            'core' => 'Core',
            'uptime' => 'Uptime',
            'security' => 'Security',
            'history' => 'History',
        ];
        $securitySupported = $this->securitySupported();
        $scanInFlight = $this->securityScanInFlight();
        $security = $securitySupported && $this->site->security_scanned_at !== null
            ? $this->securityEvaluation()
            : null;
        $hardening = (array) ($this->site->hardening ?? []);
        $hardeningLabels = [
            'hide_version' => 'Hide your WordPress version',
            'block_user_enum' => 'Block user enumeration',
            'mask_login_errors' => 'Mask login error messages',
            'disable_file_editor' => 'Disable the file editor',
            'security_headers' => 'Add security headers',
            'disable_xmlrpc' => 'Disable XML-RPC',
        ];
        $failedChecks = $security !== null ? collect($security['checks'])->reject(fn ($c) => $c['passed']) : collect();
    @endphp

    <div class="plugsent-site-strip">
        <span class="fi-badge fi-badge-size-md fi-color-{{ $connected ? 'success' : 'gray' }}">
            <span class="fi-badge-label">{{ $this->site->status }}</span>
        </span>

        <div class="plugsent-switcher" x-data="{
                open: false,
                q: '',
                tab: '{{ $tab }}',
                items: @json($this->switcherItems()),
                current: {{ $this->site->getKey() }},
                get filtered() {
                    const q = this.q.trim().toLowerCase();
                    if (q === '') return this.items;
                    return this.items.filter(i =>
                        i.name.toLowerCase().includes(q) || i.url.toLowerCase().includes(q));
                }
            }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" class="plugsent-switcher-btn" @click="open = !open">
                {{ $this->site->name }}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="plugsent-switcher-chev"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div class="plugsent-switcher-menu" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <input type="text" class="plugsent-switcher-search" placeholder="Find site…" x-model="q" autofocus>
                <div class="plugsent-switcher-list">
                    <template x-for="s in filtered" :key="s.id">
                        <a :href="s.view_url + '?tab=' + tab"
                           class="plugsent-switcher-item"
                           :class="{ 'plugsent-switcher-current': s.id === current }">
                            <strong x-text="s.name"></strong>
                            <span class="plugsent-item-slug" x-text="s.url"></span>
                        </a>
                    </template>
                    <template x-if="filtered.length === 0">
                        <p class="plugsent-empty">No matching sites.</p>
                    </template>
                </div>
                <a href="{{ \App\Filament\Resources\Sites\SiteResource::getUrl('index') }}" class="plugsent-switcher-all">
                    All sites
                </a>
            </div>
        </div>

        <a href="{{ $this->site->url }}" target="_blank" rel="noopener" class="plugsent-site-url">
            {{ $this->site->url }}
        </a>
        @if($connected)
            <span class="plugsent-meta">
                WP {{ $this->site->wp_version }} · PHP {{ $this->site->php_version }}
            </span>
            @if($this->site->last_seen_at)
                <span class="plugsent-meta">
                    Last seen {{ $this->site->last_seen_at->diffForHumans() }}
                </span>
            @endif
        @endif
    </div>

    {{-- Section tabs --}}
    <nav class="plugsent-tabs">
        @foreach($tabs as $key => $label)
            <button type="button"
                    class="plugsent-tab {{ $tab === $key ? 'plugsent-tab-active' : '' }}"
                    wire:click="switchTab('{{ $key }}')">
                {{ $label }}
                @if($key === 'security' && $security !== null && $failedChecks->count() > 0)
                    <span class="plugsent-badge plugsent-badge-danger">{{ $failedChecks->count() }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    @if($running->isNotEmpty())
        <div class="plugsent-process">
            <div class="plugsent-process-head">
                <span class="plugsent-process-spinner"></span>
                <strong>Process in progress</strong>
                <span class="plugsent-process-elapsed">
                    {{ max(0, (int) now()->diffInSeconds($running->first()->created_at)) }}s
                </span>
            </div>
            <ul class="plugsent-process-steps">
                @foreach($running as $cmd)
                    @php $inFlight = $cmd->status === \App\Models\SiteCommand::STATUS_DISPATCHED; @endphp
                    <li>
                        @if($inFlight)
                            <span class="plugsent-spin">⟳</span> {{ $this->processSubject($cmd) }}
                        @else
                            <span class="plugsent-wait">○</span> Waiting for the site · {{ $this->processSubject($cmd) }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============ Overview ============ --}}
    @if($tab === 'overview')
        <div class="plugsent-overview-grid">
            <div class="plugsent-category">
                <div class="plugsent-category-head"><h2>Security</h2></div>
                @if(! $securitySupported)
                    <p class="plugsent-empty">Update the Plugsent Connector on this site to 0.13.0+ to enable security scans and hardening.</p>
                @elseif($security === null)
                    <p class="plugsent-empty">No security scan yet — first scan queued on the site's next check-in.</p>
                @else
                    <div class="plugsent-overview-card">
                        <span class="plugsent-security-score-num plugsent-security-score-{{ $security['score'] >= 80 ? 'good' : ($security['score'] >= 50 ? 'fair' : 'poor') }}">
                            {{ $security['score'] }}<small>/100</small>
                        </span>
                        <div class="plugsent-overview-card-body">
                            @if($failedChecks->isEmpty())
                                <span class="plugsent-state plugsent-state-up">All {{ $security['checks']|count }} checks passed</span>
                            @else
                                <strong>{{ $failedChecks->count() }} item(s) need attention</strong>
                                <span class="plugsent-muted">{{ $failedChecks->take(2)->pluck('label')->implode(' · ') }}@if($failedChecks->count() > 2) · …@endif</span>
                            @endif
                            <button type="button" class="plugsent-btn plugsent-btn-sm" wire:click="switchTab('security')">Open Security</button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="plugsent-category">
                <div class="plugsent-category-head"><h2>Updates</h2></div>
                <ul class="plugsent-security-list">
                    @foreach(['plugin' => 'Plugins', 'theme' => 'Themes', 'core' => 'WordPress core'] as $ctx => $label)
                        @php $pending = $this->pendingCountFor($ctx); @endphp
                        <li>
                            <div>
                                <strong>{{ $label }}</strong>
                                <span class="plugsent-muted">{{ $pending > 0 ? $pending.' update(s) available' : 'Up to date' }}</span>
                            </div>
                            <button type="button" class="plugsent-btn plugsent-btn-sm" wire:click="switchTab('{{ $ctx }}')">Manage</button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="plugsent-category">
                <div class="plugsent-category-head"><h2>Uptime</h2></div>
                <div class="plugsent-overview-card plugsent-overview-card-col">
                    @if(! $this->site->uptime_enabled)
                        <span class="plugsent-state plugsent-state-inactive">Monitoring paused</span>
                    @elseif($this->site->uptime_status === 'up')
                        <span class="plugsent-state plugsent-state-up">Up</span>
                        <span class="plugsent-muted">Last check {{ $this->site->uptime_last_checked_at?->diffForHumans() ?? '—' }}</span>
                    @elseif($this->site->uptime_status === 'down')
                        <span class="plugsent-state plugsent-state-down">Down</span>
                        <span class="plugsent-muted">{{ $this->site->activeIncident() ? 'Ongoing incident' : 'Recently recovered' }}</span>
                    @else
                        <span class="plugsent-state plugsent-state-inactive">Waiting for first check</span>
                    @endif
                    <button type="button" class="plugsent-btn plugsent-btn-sm" wire:click="switchTab('uptime')">Uptime details</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Uptime ============ --}}
    @if($tab === 'uptime')
        @php
            $activeIncident = $this->site->activeIncident();
            $incidents = $this->recentIncidents();
            $rate = $this->uptimeRate();
        @endphp
        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Uptime &amp; monitoring</h2>
                @if($connected)
                    <button type="button" class="plugsent-btn" wire:click="toggleUptime">
                        {{ $this->site->uptime_enabled ? 'Pause monitoring' : 'Resume monitoring' }}
                    </button>
                @endif
            </div>

            <div class="plugsent-uptime-strip">
                Monitor <a href="{{ $this->site->url }}" target="_blank" rel="noopener">{{ $this->site->url }}</a>
                every {{ config('plugsent.uptime_interval_minutes', 5) }} min
            </div>

            <div class="plugsent-uptime-stats">
                <div class="plugsent-uptime-stat">
                    <span class="plugsent-uptime-stat-icon plugsent-uptime-dot-{{ $this->site->uptime_status === 'up' && $this->site->uptime_enabled ? 'up' : ($this->site->uptime_status === 'down' ? 'down' : 'idle') }}"></span>
                    <div>
                        <span class="plugsent-uptime-stat-cap">Current status</span>
                        <strong>
                            @if(! $this->site->uptime_enabled)
                                Monitoring paused
                            @elseif($this->site->uptime_status === 'up')
                                Up
                            @elseif($this->site->uptime_status === 'down')
                                Down
                            @else
                                Waiting for first check
                            @endif
                        </strong>
                    </div>
                </div>
                <div class="plugsent-uptime-stat">
                    <span class="plugsent-uptime-stat-icon">🌐</span>
                    <div>
                        <span class="plugsent-uptime-stat-cap">Domain expires</span>
                        <strong>
                            @if($this->site->domain_expires_at)
                                {{ $this->site->domain_expires_at->diffForHumans(['parts' => 2]) }}
                                <span class="plugsent-muted">({{ $this->site->domain_expires_at->format('M j, Y') }})</span>
                            @else
                                —
                            @endif
                        </strong>
                    </div>
                </div>
                <div class="plugsent-uptime-stat">
                    <span class="plugsent-uptime-stat-icon">🔒</span>
                    <div>
                        <span class="plugsent-uptime-stat-cap">SSL certificate expires</span>
                        <strong>
                            @if($this->site->ssl_expires_at)
                                {{ $this->site->ssl_expires_at->diffForHumans(['parts' => 2]) }}
                                <span class="plugsent-muted">({{ $this->site->ssl_expires_at->format('M j, Y') }})</span>
                            @else
                                —
                            @endif
                        </strong>
                    </div>
                </div>
                <div class="plugsent-uptime-stat">
                    <span class="plugsent-uptime-stat-icon">⏱</span>
                    <div>
                        <span class="plugsent-uptime-stat-cap">Last check</span>
                        <strong>{{ $this->site->uptime_last_checked_at?->diffForHumans() ?? '—' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="plugsent-category">
            <div class="plugsent-category-head"><h2>Uptime rate</h2></div>
            <div class="plugsent-uptime-rate">
                <div class="plugsent-uptime-rate-pct plugsent-security-score-{{ $rate['pct'] >= 99.5 ? 'good' : ($rate['pct'] >= 95 ? 'fair' : 'poor') }}">
                    {{ number_format($rate['pct'], 2) }}%
                </div>
                <div class="plugsent-uptime-rate-caption">uptime over 30 days</div>
                <div class="plugsent-uptime-bars">
                    @foreach($rate['days'] as $day)
                        <span class="plugsent-uptime-bar {{ $day['downtime_seconds'] === 0 ? 'plugsent-uptime-bar-up' : 'plugsent-uptime-bar-down' }}"
                              title="{{ $day['label'] }}"></span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Incidents</h2>
                <span class="plugsent-meta">
                    @if($activeIncident)
                        ⚠ Ongoing since {{ $activeIncident->started_at->diffForHumans() }}
                    @else
                        {{ $incidents->whereNotNull('ended_at')->count() }} on record
                    @endif
                </span>
            </div>
            @if($incidents->isEmpty())
                <p class="plugsent-empty">No incidents recorded — checks started recently.</p>
            @else
                <ul class="plugsent-incident-list">
                    @foreach($incidents as $incident)
                        <li class="plugsent-incident">
                            <span class="plugsent-incident-icon {{ $incident->isActive() ? 'plugsent-incident-down' : 'plugsent-incident-up' }}">
                                {{ $incident->isActive() ? '✕' : '✓' }}
                            </span>
                            <div>
                                <strong>
                                    @if($incident->isActive())
                                        Down since {{ $incident->started_at->format('M j, H:i') }}
                                    @else
                                        Back online
                                    @endif
                                </strong>
                                <span class="plugsent-muted">
                                    {{ $incident->started_at->format('M j, Y H:i') }}
                                    @if($incident->ended_at)
                                        · down for {{ $incident->started_at->longAbsoluteDiffForHumans($incident->ended_at, 2) }}
                                    @endif
                                </span>
                                @if($incident->last_error || $incident->last_status_code)
                                    <span class="plugsent-muted">
                                        {{ $incident->last_error ? \Illuminate\Support\Str::limit($incident->last_error, 70) : 'HTTP '.$incident->last_status_code }}
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- ============ Plugins / Themes / Core ============ --}}
    @if(in_array($tab, ['plugins', 'themes', 'core'], true))
        @php
            $context = ['plugins' => 'plugin', 'themes' => 'theme', 'core' => 'core'][$tab];
            $label = ['plugins' => 'Plugins', 'themes' => 'Themes', 'core' => 'WordPress core'][$tab];
            $items = $this->getInventoryFor($context);
            $pending = $items->where('update_available', true)->count();
        @endphp
        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>{{ $label }}</h2>
                @if($pending > 0 && $connected)
                    <button type="button" class="plugsent-btn" wire:click="updateCategory('{{ $context }}')">
                        Update all ({{ $pending }})
                    </button>
                @endif
            </div>

            <div class="plugsent-table-wrap">
                <table class="plugsent-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Installed</th>
                            <th>Available update</th>
                            <th>Update status</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php
                                $status = $this->statusFor($item);
                                $inFlight = $this->inFlightFor($item);
                                $isExcluded = in_array($context.'|'.$item->slug, $excluded, true);
                                $manageable = $connected && $context !== 'core' && ! $inFlight
                                    && $item->slug !== 'plugsent-connector';
                            @endphp
                            <tr>
                                <td>
                                    <div class="plugsent-item-name">{{ $item->name }}</div>
                                    <div class="plugsent-item-slug">{{ $item->slug }}</div>
                                </td>
                                <td>{{ $item->version }}</td>
                                <td>
                                    @if($item->update_available)
                                        <span class="plugsent-version-new">{{ $item->update_version }}</span>
                                    @else
                                        <span class="plugsent-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($status)
                                        <span class="plugsent-status plugsent-status-{{ \Illuminate\Support\Str::of($status)->before('…')->slug('_') }}">
                                            {{ $status }}
                                        </span>
                                    @else
                                        <span class="plugsent-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="plugsent-state plugsent-state-{{ $item->active ? 'active' : 'inactive' }}">
                                        {{ $item->active ? 'active' : 'inactive' }}
                                    </span>
                                    @if(($item->vuln_count ?? 0) > 0)
                                        <button type="button" class="plugsent-state plugsent-state-down plugsent-vuln-badge"
                                                title="View vulnerability details"
                                                wire:click="openVulnerabilities('{{ $context }}', '{{ $item->slug }}', @js($item->name), {{ $item->update_available ? 'true' : 'false' }})">
                                            ⚠ {{ $item->vuln_count }} vulnerable
                                        </button>
                                    @endif
                                </td>
                                <td class="plugsent-cell-actions">
                                    @if($connected && $item->update_available && ! $inFlight && ! $isExcluded)
                                        <button type="button" class="plugsent-btn plugsent-btn-icon plugsent-btn-primary"
                                                title="Update to {{ $item->update_version }}" aria-label="Update"
                                                wire:click="requestUpdate('{{ $context }}', '{{ $item->slug }}')">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l3-3m0 0l3 3m-3-3v7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                    @endif

                                    @if($manageable && $context === 'plugin')
                                        @if($this->site->supportsCommand('plugin.activate'))
                                            @if($item->active)
                                                <button type="button" class="plugsent-btn plugsent-btn-icon"
                                                        title="Deactivate" aria-label="Deactivate"
                                                        wire:click="requestAction('plugin.deactivate', '{{ $item->slug }}')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" /></svg>
                                                </button>
                                            @else
                                                <button type="button" class="plugsent-btn plugsent-btn-icon"
                                                        title="Activate" aria-label="Activate"
                                                        wire:click="requestAction('plugin.activate', '{{ $item->slug }}')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                                                </button>
                                            @endif
                                        @endif
                                        @if($this->site->supportsCommand('plugin.delete'))
                                            <button type="button" class="plugsent-btn plugsent-btn-icon plugsent-btn-icon-danger"
                                                    title="Delete plugin" aria-label="Delete plugin"
                                                    wire:click="requestAction('plugin.delete', '{{ $item->slug }}')"
                                                    wire:confirm="Delete {{ $item->name }} from {{ $this->site->name }}? This permanently removes its files from the site.">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        @endif
                                    @elseif($manageable && $context === 'theme')
                                        @if(! $item->active && $this->site->supportsCommand('theme.activate'))
                                            <button type="button" class="plugsent-btn plugsent-btn-icon"
                                                    title="Activate theme" aria-label="Activate theme"
                                                    wire:click="requestAction('theme.activate', '{{ $item->slug }}')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                                            </button>
                                        @endif
                                        @if(! $item->active && $this->site->supportsCommand('theme.delete'))
                                            <button type="button" class="plugsent-btn plugsent-btn-icon plugsent-btn-icon-danger"
                                                    title="Delete theme" aria-label="Delete theme"
                                                    wire:click="requestAction('theme.delete', '{{ $item->slug }}')"
                                                    wire:confirm="Delete the {{ $item->name }} theme from {{ $this->site->name }}? This permanently removes its files from the site.">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        @endif
                                    @endif

                                    @if($connected && $context !== 'core')
                                        <button type="button" class="plugsent-btn plugsent-btn-icon"
                                                title="{{ $isExcluded ? 'Include in updates' : 'Exclude from updates' }}"
                                                aria-label="{{ $isExcluded ? 'Include in updates' : 'Exclude from updates' }}"
                                                wire:click="toggleUpdateExclusion('{{ $context }}', '{{ $item->slug }}')">
                                            @if($isExcluded)
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                            @endif
                                        </button>
                                        @if($isExcluded)
                                            <span class="plugsent-state plugsent-state-inactive">excluded</span>
                                        @endif
                                    @endif

                                    @if($connected && $context !== 'core' && ! $inFlight
                                        && $this->canRestore()
                                        && $this->site->supportsCommand('restore.apply')
                                        && in_array($context.'|'.$item->slug, $restorableKeys, true))
                                        <button type="button" class="plugsent-btn plugsent-btn-icon"
                                                title="Restore backup" aria-label="Restore backup"
                                                wire:click="requestRestore('{{ $context }}', '{{ $item->slug }}')"
                                                wire:confirm="Restore {{ $item->name }} on {{ $this->site->name }} to its backed-up version? Its files and database are restored to the state before the last update.">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="plugsent-empty">Nothing reported yet — the site sends its inventory on each check-in.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============ Security ============ --}}
    @if($tab === 'security')
        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Site health</h2>
                @if($connected && $securitySupported)
                    <button type="button" class="plugsent-btn" wire:click="runSecurityScan" @if($scanInFlight) disabled @endif>
                        @if($scanInFlight) Scanning… @else Re-scan @endif
                    </button>
                @endif
            </div>

            @if(! $securitySupported)
                <p class="plugsent-empty">
                    This site runs an older Plugsent Connector. Update it to 0.13.0+ on the site to enable
                    security scans and hardening.
                </p>
            @elseif($this->site->security_scanned_at === null)
                <p class="plugsent-empty">
                    No security scan yet. A scan is queued and will complete on the site's next check-in.
                </p>
            @else
                <div class="plugsent-health-grid">
                    <div class="plugsent-health-score">
                        <span class="plugsent-security-score-num plugsent-security-score-{{ $security['score'] >= 80 ? 'good' : ($security['score'] >= 50 ? 'fair' : 'poor') }}">
                            {{ $security['score'] }}
                        </span>
                        <span class="plugsent-security-score-cap">
                            /100<br>
                            <span class="plugsent-muted">scanned {{ $this->site->security_scanned_at->diffForHumans() }}</span>
                        </span>
                    </div>

                    <div class="plugsent-security-checks">
                        <h3>Attention needed <span class="plugsent-badge plugsent-badge-danger">{{ $failedChecks->count() }}</span></h3>
                        @if($failedChecks->isEmpty())
                            <p class="plugsent-muted">Everything checks out.</p>
                        @else
                            <ul class="plugsent-security-list">
                                @foreach($failedChecks as $check)
                                    <li>
                                        <div>
                                            <strong>{{ $check['label'] }}</strong>
                                            <span class="plugsent-muted">{{ $check['detail'] }}</span>
                                        </div>
                                        @if($check['fix'] && $connected)
                                            <button type="button" class="plugsent-btn plugsent-btn-sm"
                                                    wire:click="requestHardening('{{ $check['fix'] }}', true)"
                                                    @if($this->hardeningInFlight($check['fix'], true)) disabled @endif>
                                                @if($this->hardeningInFlight($check['fix'], true)) Applying… @else Fix @endif
                                            </button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <h3 class="plugsent-checks-heading">Passed <span class="plugsent-badge plugsent-badge-ok">{{ count($security['checks']) - $failedChecks->count() }}</span></h3>
                        <ul class="plugsent-security-list plugsent-security-list-passed">
                            @foreach(collect($security['checks'])->filter(fn ($c) => $c['passed']) as $check)
                                <li><strong>{{ $check['label'] }}</strong> <span class="plugsent-muted">{{ $check['detail'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Protections</h2>
            </div>
            @if($securitySupported && $this->site->security_scanned_at !== null)
                <div class="plugsent-hardening-grid">
                    @foreach($hardeningLabels as $key => $hlabel)
                        @php
                            $on = (bool) ($hardening[$key] ?? false);
                            $toggling = $this->hardeningInFlight($key, ! $on);
                        @endphp
                        <div class="plugsent-hardening-item">
                            <div>
                                <strong>{{ $hlabel }}</strong>
                                <span class="plugsent-state plugsent-state-{{ $on ? 'up' : 'inactive' }}">{{ $on ? 'on' : 'off' }}</span>
                            </div>
                            <button type="button" class="plugsent-btn plugsent-btn-sm"
                                    wire:click="requestHardening('{{ $key }}', {{ $on ? 'false' : 'true' }})"
                                    @if($toggling) disabled @endif>
                                @if($toggling) Applying… @elseif($on) Turn off @else Turn on @endif
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="plugsent-empty">Protections become available after the first security scan (connector 0.13.0+).</p>
            @endif
        </div>

        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Vulnerabilities</h2>
            </div>
            @php $vulnerableItems = $this->site->inventory()->where('vuln_count', '>', 0)->orderByDesc('vuln_count')->get(); @endphp
            @if($vulnerableItems->isEmpty())
                <p class="plugsent-empty">No known vulnerable plugins or themes on this site.</p>
            @else
                <div class="plugsent-table-wrap">
                    <table class="plugsent-table">
                        <thead>
                            <tr><th>Name</th><th>Installed</th><th>Vulnerabilities</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($vulnerableItems as $item)
                                <tr>
                                    <td>
                                        <div class="plugsent-item-name">{{ $item->name }}</div>
                                        <div class="plugsent-item-slug">{{ $item->slug }}</div>
                                    </td>
                                    <td>{{ $item->version }}</td>
                                    <td>
                                        <span class="plugsent-state plugsent-state-down">⚠ {{ $item->vuln_count }}</span>
                                        @if($item->update_available)
                                            <span class="plugsent-state plugsent-state-up">fix available: {{ $item->update_version }}</span>
                                        @endif
                                    </td>
                                    <td class="plugsent-cell-actions">
                                        <button type="button" class="plugsent-btn plugsent-btn-sm"
                                                wire:click="openVulnerabilities('{{ $item->context }}', '{{ $item->slug }}', @js($item->name), {{ $item->update_available ? 'true' : 'false' }})">
                                            Details
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- ============ History ============ --}}
    @if($tab === 'history')
        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Activity history</h2>
            </div>
            <div class="plugsent-table-wrap">
                <table class="plugsent-table">
                    <thead>
                        <tr><th>When</th><th>Action</th><th>Status</th><th>Detail</th></tr>
                    </thead>
                    <tbody>
                        @forelse($this->history() as $cmd)
                            <tr>
                                <td>
                                    <div>{{ $cmd->created_at->format('M j, H:i') }}</div>
                                    <div class="plugsent-item-slug">{{ $cmd->created_at->diffForHumans() }}</div>
                                </td>
                                <td>{{ $this->processSubject($cmd) }}</td>
                                <td>
                                    @if($cmd->status === \App\Models\SiteCommand::STATUS_COMPLETED)
                                        <span class="plugsent-state plugsent-state-up">completed</span>
                                    @elseif($cmd->status === \App\Models\SiteCommand::STATUS_FAILED)
                                        <span class="plugsent-state plugsent-state-down">failed</span>
                                    @else
                                        <span class="plugsent-state plugsent-state-inactive">{{ $cmd->status }}</span>
                                    @endif
                                </td>
                                <td class="plugsent-muted">
                                    {{ \Illuminate\Support\Str::limit($cmd->result['error'] ?? ($cmd->result['data']['update']['message'] ?? ($cmd->result['data']['safe']['message'] ?? '')) ?: '', 90) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="plugsent-empty">No activity recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Vulnerability detail modal --}}
    @if($this->vulnDetail !== null)
        @php
            $vulns = $this->vulnerabilitiesFor($this->vulnDetail['context'], $this->vulnDetail['slug']);
            $hasUpdate = $this->vulnDetail['update_available'];
        @endphp
        <div class="plugsent-vuln-overlay" wire:click="closeVulnerabilities" x-data x-cloak>
            <div class="plugsent-vuln-modal" wire:click.stop x-on:keydown.escape.window="$wire.closeVulnerabilities()">
                <div class="plugsent-vuln-head">
                    <div>
                        <h3>{{ $this->vulnDetail['name'] }}</h3>
                        <span class="plugsent-item-slug">{{ $this->vulnDetail['slug'] }}</span>
                    </div>
                    <button type="button" class="plugsent-btn plugsent-btn-icon" aria-label="Close"
                            wire:click="closeVulnerabilities">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="plugsent-vuln-body">
                    @forelse($vulns as $vuln)
                        @php
                            $bucket = \App\Filament\Resources\Sites\Pages\ViewSite::cvssBucket($vuln->cvss !== null ? (float) $vuln->cvss : null);
                            $range = \App\Filament\Resources\Sites\Pages\ViewSite::affectedRangeText($vuln);
                            $description = \App\Filament\Resources\Sites\Pages\ViewSite::descriptionFor($vuln);
                            $references = \App\Filament\Resources\Sites\Pages\ViewSite::referencesFor($vuln);
                        @endphp
                        <div class="plugsent-vuln-card">
                            <div class="plugsent-vuln-card-head">
                                <span class="plugsent-vuln-severity plugsent-vuln-severity-{{ $bucket }}">
                                    {{ ucfirst($bucket) }}{{ $vuln->cvss !== null ? ' · CVSS '.$vuln->cvss : '' }}
                                </span>
                                <span class="plugsent-muted">
                                    {{ $vuln->published_at?->format('M j, Y') ?? 'Unknown date' }}
                                </span>
                            </div>

                            <p class="plugsent-vuln-title">{{ $vuln->title }}</p>

                            <dl class="plugsent-vuln-facts">
                                @if($vuln->cve)
                                    <div>
                                        <dt>CVE</dt>
                                        <dd>
                                            <a href="https://nvd.nist.gov/vuln/detail/{{ $vuln->cve }}" target="_blank" rel="noopener">{{ $vuln->cve }}</a>
                                        </dd>
                                    </div>
                                @endif
                                <div>
                                    <dt>Affects</dt>
                                    <dd>{{ $range }}</dd>
                                </div>
                                <div>
                                    <dt>Fix status</dt>
                                    <dd>
                                        @if($vuln->patched)
                                            <span class="plugsent-state plugsent-state-up">Patched{{ $vuln->patched_version ? ' in '.$vuln->patched_version : '' }}</span>
                                        @else
                                            <span class="plugsent-state plugsent-state-down">No patch available</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            @if($description)
                                <p class="plugsent-vuln-description">{{ $description }}</p>
                            @endif

                            <div class="plugsent-vuln-advice">
                                @if($vuln->patched)
                                    @if($hasUpdate)
                                        Update it — a patched release is available. Use the update button on the row above, or "Update all".
                                    @else
                                        Update <strong>{{ $this->vulnDetail['name'] }}</strong> to
                                        {{ $vuln->patched_version ? 'version '.$vuln->patched_version : 'the latest version' }}
                                        as soon as an update is published.
                                    @endif
                                @else
                                    No official patch exists yet. Consider deactivating or removing
                                    <strong>{{ $this->vulnDetail['name'] }}</strong> until the vendor ships a fix,
                                    and review the references below for mitigation details.
                                @endif
                            </div>

                            <div class="plugsent-vuln-links">
                                <a href="https://www.wordfence.com/threat-intel/vulnerabilities/ids/{{ preg_replace('/-r\d+$/', '', $vuln->external_id) }}.html" target="_blank" rel="noopener">
                                    Wordfence entry ↗
                                </a>
                                @foreach($references as $refUrl)
                                    <a href="{{ $refUrl }}" target="_blank" rel="noopener">
                                        {{ parse_url($refUrl, PHP_URL_HOST) }} ↗
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="plugsent-empty">No matching records in the local feed — try re-syncing the vulnerability feed in Settings.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
