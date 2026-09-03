<x-filament-panels::page wire:poll.3s>
    @php
        $connected = $this->site->isConnected();
        $batch = $this->currentBatch();
        $elapsed = $batch['elapsed'] ?? 0;
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

    @if($batch)
        <x-filament::section class="plugsent-process">
            <x-slot:heading>
                {{ $batch['finished'] ? 'Process finished' : 'Process in progress' }}
            </x-slot:heading>
            <x-slot:description>
                Running for {{ $elapsed }}s — {{ $batch['done'] }} / {{ $batch['total'] }} updates done. This page updates itself.
            </x-slot:description>

            <div class="plugsent-process-steps">
                @foreach($batch['commands'] as $cmd)
                    @php
                        $subject = match ($cmd->type) {
                            'update.run' => ($cmd->payload['slug'] ?? ''),
                            'inventory.get' => 'Refreshing inventory',
                            default => $cmd->type,
                        };
                    @endphp
                    <div class="plugsent-step plugsent-step-{{ $cmd->status }}">
                        <span class="plugsent-step-icon plugsent-step-icon-{{ $cmd->status }} {{ $cmd->status === 'dispatched' ? 'plugsent-spin' : '' }}">
                            @if($cmd->status === 'completed')
                                <x-filament::icon icon="heroicon-m-check-circle" />
                            @elseif($cmd->status === 'failed')
                                <x-filament::icon icon="heroicon-m-x-circle" />
                            @elseif($cmd->status === 'dispatched')
                                <x-filament::icon icon="heroicon-m-arrow-path" />
                            @else
                                <span class="plugsent-step-wait"></span>
                            @endif
                        </span>
                        <span class="plugsent-step-label">
                            @if($cmd->status === 'completed')
                                Updated
                            @elseif($cmd->status === 'failed')
                                Failed
                            @elseif($cmd->status === 'dispatched')
                                Updating
                            @else
                                Waiting for the site
                            @endif
                            <span class="plugsent-step-subject">· {{ $subject }}</span>
                        </span>
                    </div>
                @endforeach
            </div>

            @if($batch['total'] > 0)
                <div class="plugsent-progress">
                    <div class="plugsent-progress-label">
                        {{ $batch['percent'] }}% — {{ $batch['done'] }} / {{ $batch['total'] }} updated
                    </div>
                    <div class="plugsent-progress-track">
                        <div class="plugsent-progress-fill" style="width: {{ max(4, $batch['percent']) }}%"></div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
