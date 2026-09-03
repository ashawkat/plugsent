<x-filament-panels::page wire:poll.3s>
    @php
        $connected = $this->site->isConnected();
        $running = $this->runningProcesses();
        $progress = $this->processProgress();
        $elapsed = $running->isNotEmpty() ? (int) abs(now()->diffInSeconds($running->first()->created_at)) : 0;
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
        <x-filament::section class="plugsent-process">
            <x-slot:heading>
                Process in progress
            </x-slot:heading>
            <x-slot:description>
                Running for {{ $elapsed }}s — this page updates itself.
            </x-slot:description>

            <div class="plugsent-process-steps">
                @foreach($running as $cmd)
                    @php
                        $subject = match ($cmd->type) {
                            'update.run' => ($cmd->payload['slug'] ?? ''),
                            'inventory.get' => 'Refreshing inventory',
                            default => $cmd->type,
                        };
                        $inFlight = $cmd->status === \App\Models\SiteCommand::STATUS_DISPATCHED;
                    @endphp
                    <div class="plugsent-step {{ $inFlight ? 'plugsent-step-active' : '' }}">
                        <span class="plugsent-step-icon {{ $inFlight ? 'plugsent-spin' : '' }}">
                            @if($inFlight)
                                <x-filament::icon icon="heroicon-m-arrow-path" />
                            @else
                                <span class="plugsent-step-wait"></span>
                            @endif
                        </span>
                        <span class="plugsent-step-label">
                            {{ $inFlight ? 'Updating' : 'Waiting for the site' }}
                            <span class="plugsent-step-subject">· {{ $subject }}</span>
                        </span>
                    </div>
                @endforeach
            </div>

            @if($progress['total'] > 0)
                <div class="plugsent-progress">
                    <div class="plugsent-progress-label">
                        {{ $progress['percent'] }}% — {{ $progress['done'] }} / {{ $progress['total'] }} updated
                    </div>
                    <div class="plugsent-progress-track">
                        <div class="plugsent-progress-fill" style="width: {{ max(4, $progress['percent']) }}%"></div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
