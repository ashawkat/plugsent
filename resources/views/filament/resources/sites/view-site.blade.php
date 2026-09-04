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
                                $isExcluded = in_array($context.'|'.$item->slug, $excluded, true);
                                $manageable = $connected && $context !== 'core' && ! $status
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
                                    @if($connected && $item->update_available && ! $status && ! $isExcluded)
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
