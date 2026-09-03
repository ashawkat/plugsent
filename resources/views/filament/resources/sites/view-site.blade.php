<x-filament-panels::page>
    @php
        $connected = $this->site->isConnected();
        $running = $this->runningProcesses();
        $progress = $this->processProgress();
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-2">
        <span class="fi-badge fi-badge-size-md fi-color-{{ $connected ? 'success' : 'gray' }}">
            <span class="fi-badge-label">{{ $this->site->status }}</span>
        </span>
        <a href="{{ $this->site->url }}" target="_blank" rel="noopener" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
            {{ $this->site->url }}
        </a>
        @if($connected)
            <span class="text-sm text-gray-500 dark:text-gray-400">
                WP {{ $this->site->wp_version }} · PHP {{ $this->site->php_version }}
            </span>
            @if($this->site->last_seen_at)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Last seen {{ $this->site->last_seen_at->diffForHumans() }}
                </span>
            @endif
        @endif
    </div>

    @if($running->isNotEmpty())
        <div class="fi-section mb-4 rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/20 dark:bg-primary-500/10">
            <div class="flex items-baseline gap-2">
                <h3 class="text-base font-semibold">Process in progress</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    · {{ now()->diffInSeconds($running->first()->created_at) }}s
                </span>
            </div>

            <ul class="mt-2 space-y-1.5">
                @foreach($running as $cmd)
                    @php
                        $subject = match ($cmd->type) {
                            'update.run' => ($cmd->payload['slug'] ?? ''),
                            'inventory.get' => 'Refreshing inventory',
                            default => $cmd->type,
                        };
                        $step = $cmd->status === \App\Models\SiteCommand::STATUS_DISPATCHED
                            ? 'Updating'
                            : 'Waiting for the site';
                    @endphp
                    <li class="flex items-center gap-2 text-sm">
                        @if($cmd->status === \App\Models\SiteCommand::STATUS_DISPATCHED)
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 text-primary-500 animate-spin" />
                            <span class="font-medium">Updating</span>
                            <span class="text-gray-500 dark:text-gray-400">· {{ $subject }}</span>
                        @else
                            <span class="inline-block h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                            <span class="text-gray-500 dark:text-gray-400">Waiting for the site · {{ $subject }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if($progress['total'] > 0)
                <div class="mt-3">
                    <div class="text-sm font-medium">{{ $progress['percent'] }}% — {{ $progress['done'] }} / {{ $progress['total'] }}</div>
                    <div class="mt-1 h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-primary-600 transition-all duration-500"
                             style="width: {{ max(5, $progress['percent']) }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
