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
    @endforeach
</x-filament-panels::page>
