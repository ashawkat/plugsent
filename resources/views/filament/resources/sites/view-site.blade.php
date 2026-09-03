<x-filament-panels::page>
    @php
        $connected = $this->site->isConnected();
        $sections = [
            'plugin' => 'Plugins',
            'theme' => 'Themes',
            'core' => 'WordPress core',
        ];
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
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
            <button type="button" wire:click="refreshInventory"
                    class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                Refresh inventory
            </button>
        @endif
    </div>

    @foreach($sections as $context => $label)
        @php
            $items = $this->site->inventory()->where('context', $context)->orderBy('name')->get();
            $pending = $items->where('update_available', true)->count();
        @endphp

        <x-filament::section class="mb-4">
            <x-slot:heading>
                {{ $label }} ({{ $items->count() }})
                @if($pending > 0)
                    <span class="fi-badge fi-badge-size-sm fi-color-danger ml-2">
                        <span class="fi-badge-label">{{ $pending }} update{{ $pending > 1 ? 's' : '' }} available</span>
                    </span>
                @endif
            </x-slot:heading>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-start">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/10 text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2.5 text-start font-medium">Name</th>
                            <th class="px-4 py-2.5 text-start font-medium">Version</th>
                            <th class="px-4 py-2.5 text-start font-medium">State</th>
                            <th class="px-4 py-2.5 text-end font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-gray-100 dark:border-white/10">
                                <td class="px-4 py-3 font-medium">
                                    {{ $item->name }}
                                    <span class="text-gray-400">{{ $item->slug }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($item->update_available)
                                        <span class="text-danger-600 dark:text-danger-400">{{ $item->version }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="text-success-600 dark:text-success-400 font-medium">{{ $item->update_version }}</span>
                                    @else
                                        {{ $item->version }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="fi-badge fi-badge-size-sm fi-color-{{ $item->active ? 'success' : 'gray' }}">
                                        <span class="fi-badge-label">{{ $item->active ? 'active' : 'inactive' }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    @if($connected && $item->update_available && $context !== 'core')
                                        <x-filament::button size="sm" wire:click="requestUpdate('{{ $context }}', '{{ $item->slug }}')">
                                            Update
                                        </x-filament::button>
                                    @elseif($connected && $item->update_available && $context === 'core')
                                        <x-filament::button size="sm" color="danger" wire:click="requestUpdate('core', 'wordpress')"
                                                            wire:confirm="Update WordPress core on this site?">
                                            Update core
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                    Nothing reported yet — the site sends its inventory on each check-in.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
