<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3 mb-2">
        <span class="fi-badge fi-badge-size-md fi-color-{{ $this->site->isConnected() ? 'success' : 'gray' }}">
            <span class="fi-badge-label">{{ $this->site->status }}</span>
        </span>
        <a href="{{ $this->site->url }}" target="_blank" rel="noopener" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
            {{ $this->site->url }}
        </a>
        @if($this->site->isConnected())
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

    {{ $this->content }}
</x-filament-panels::page>
