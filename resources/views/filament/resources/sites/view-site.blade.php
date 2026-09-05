<x-filament-panels::page wire:poll.3s>
    @php
        $connected = $this->site->isConnected();
        $running = $this->runningProcesses();
        $sections = [
            'plugin' => 'Plugins',
            'theme' => 'Themes',
            'core' => 'WordPress core',
        ];
    @endphp

    <div class="plugsent-site-strip">
        <span class="fi-badge fi-badge-size-md fi-color-{{ $connected ? 'success' : 'gray' }}">
            <span class="fi-badge-label">{{ $this->site->status }}</span>
        </span>
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

    {{-- Uptime --}}
    @php
        $activeIncident = $this->site->activeIncident();
        $incidents = $this->recentIncidents();
    @endphp
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Uptime</h2>
            @if($connected)
                <button type="button" class="plugsent-btn" wire:click="toggleUptime">
                    {{ $this->site->uptime_enabled ? 'Pause monitoring' : 'Resume monitoring' }}
                </button>
            @endif
        </div>

        <div class="plugsent-form-grid cols-3" style="padding-top: 14px;">
            <div class="plugsent-field">
                <label>Status</label>
                <div>
                    @if(! $this->site->uptime_enabled)
                        <span class="plugsent-state plugsent-state-inactive">Monitoring paused</span>
                    @elseif($this->site->uptime_status === 'up')
                        <span class="plugsent-state plugsent-state-up">Up</span>
                    @elseif($this->site->uptime_status === 'down')
                        <span class="plugsent-state plugsent-state-down">Down</span>
                    @else
                        <span class="plugsent-state plugsent-state-inactive">Waiting for first check</span>
                    @endif
                </div>
            </div>
            <div class="plugsent-field">
                <label>Last check</label>
                <div class="plugsent-meta">
                    @if($this->site->uptime_last_checked_at)
                        {{ $this->site->uptime_last_checked_at->diffForHumans() }}
                        @if($this->site->uptime_last_response_ms !== null)
                            · {{ number_format($this->site->uptime_last_response_ms) }} ms
                        @endif
                        @if($this->site->uptime_last_status_code)
                            · HTTP {{ $this->site->uptime_last_status_code }}
                        @elseif($this->site->uptime_last_error)
                            · {{ \Illuminate\Support\Str::limit($this->site->uptime_last_error, 60) }}
                        @endif
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="plugsent-field">
                <label>Downtime</label>
                <div class="plugsent-meta">
                    @if($activeIncident)
                        ⚠ Ongoing since {{ $activeIncident->started_at->diffForHumans() }}
                    @else
                        {{ $incidents->whereNotNull('ended_at')->count() }} incident(s) on record
                    @endif
                </div>
            </div>
        </div>

        @if($incidents->isNotEmpty())
            <div class="plugsent-table-wrap" style="padding-top: 6px;">
                <table class="plugsent-table">
                    <thead>
                        <tr><th>Started</th><th>Duration</th><th>Detail</th></tr>
                    </thead>
                    <tbody>
                        @foreach($incidents as $incident)
                            <tr>
                                <td>{{ $incident->started_at->format('M j, H:i') }}</td>
                                <td>
                                    @if($incident->isActive())
                                        <span class="plugsent-state plugsent-state-down">ongoing</span>
                                    @else
                                        {{ $incident->started_at->diffForHumans($incident->ended_at, ['parts' => 2]) }}
                                    @endif
                                </td>
                                <td class="plugsent-muted">
                                    @if($incident->last_error)
                                        {{ \Illuminate\Support\Str::limit($incident->last_error, 80) }}
                                    @else
                                        HTTP {{ $incident->last_status_code ?? '?' }} · {{ $incident->failure_count }} failed checks
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

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

    @php
        $excluded = $this->excludedKeys();
        $restorableKeys = $this->restorableKeys();
    @endphp

    @foreach($sections as $context => $label)
        @php
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
                                </td>
                                <td class="plugsent-cell-actions">
                                    @if($connected && $item->update_available && ! $inFlight && ! $isExcluded)
                                        <button type="button" class="plugsent-btn plugsent-btn-primary"
                                                wire:click="requestUpdate('{{ $context }}', '{{ $item->slug }}')">
                                            Update
                                        </button>
                                    @endif

                                    @if($manageable && $context === 'plugin')
                                        @if($this->site->supportsCommand('plugin.activate'))
                                            @if($item->active)
                                                <button type="button" class="plugsent-btn"
                                                        wire:click="requestAction('plugin.deactivate', '{{ $item->slug }}')">
                                                    Deactivate
                                                </button>
                                            @else
                                                <button type="button" class="plugsent-btn"
                                                        wire:click="requestAction('plugin.activate', '{{ $item->slug }}')">
                                                    Activate
                                                </button>
                                            @endif
                                        @endif
                                        @if($this->site->supportsCommand('plugin.delete'))
                                            <button type="button" class="plugsent-btn"
                                                    wire:click="requestAction('plugin.delete', '{{ $item->slug }}')"
                                                    wire:confirm="Delete {{ $item->name }} from {{ $this->site->name }}? This permanently removes its files from the site.">
                                                Delete
                                            </button>
                                        @endif
                                    @elseif($manageable && $context === 'theme')
                                        @if(! $item->active && $this->site->supportsCommand('theme.activate'))
                                            <button type="button" class="plugsent-btn"
                                                    wire:click="requestAction('theme.activate', '{{ $item->slug }}')">
                                                Activate
                                            </button>
                                        @endif
                                        @if(! $item->active && $this->site->supportsCommand('theme.delete'))
                                            <button type="button" class="plugsent-btn"
                                                    wire:click="requestAction('theme.delete', '{{ $item->slug }}')"
                                                    wire:confirm="Delete the {{ $item->name }} theme from {{ $this->site->name }}? This permanently removes its files from the site.">
                                                Delete
                                            </button>
                                        @endif
                                    @endif

                                    @if($connected && $context !== 'core')
                                        <button type="button" class="plugsent-btn"
                                                wire:click="toggleUpdateExclusion('{{ $context }}', '{{ $item->slug }}')">
                                            {{ $isExcluded ? 'Include updates' : 'Exclude updates' }}
                                        </button>
                                        @if($isExcluded)
                                            <span class="plugsent-state plugsent-state-inactive">excluded</span>
                                        @endif
                                    @endif

                                    @if($connected && $context !== 'core' && ! $inFlight
                                        && $this->canRestore()
                                        && $this->site->supportsCommand('restore.apply')
                                        && in_array($context.'|'.$item->slug, $restorableKeys, true))
                                        <button type="button" class="plugsent-btn"
                                                wire:click="requestRestore('{{ $context }}', '{{ $item->slug }}')"
                                                wire:confirm="Restore {{ $item->name }} on {{ $this->site->name }} to its backed-up version? Its files and database are restored to the state before the last update.">
                                            Restore backup
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
    @endforeach
</x-filament-panels::page>
