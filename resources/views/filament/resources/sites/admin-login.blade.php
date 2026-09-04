<x-filament-panels::page wire:poll.2s="checkForLoginUrl">
    @php
        $outstanding = ! $this->error && \App\Models\SiteCommand::query()
            ->where('site_id', $this->site->getKey())
            ->where('type', 'admin.login')
            ->whereIn('status', [\App\Models\SiteCommand::STATUS_PENDING, \App\Models\SiteCommand::STATUS_DISPATCHED])
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();
    @endphp

    @if($this->error)
        <x-filament::section class="plugsent-process">
            <x-slot:heading>
                Couldn't open WordPress admin
            </x-slot:heading>
            <x-slot:description>
                {{ $this->error }}
            </x-slot:description>

            <x-filament::button color="primary" wire:click="enqueue">
                Retry
            </x-filament::button>
        </x-filament::section>
    @elseif($outstanding)
        <div class="plugsent-process plugsent-connecting">
            <div class="plugsent-process-head">
                <span class="plugsent-process-spinner">⟳</span>
                <strong>Opening WordPress admin for {{ $this->site->name }}…</strong>
            </div>
            <p class="plugsent-process-note">
                The site is generating a one-time login link. You'll be redirected automatically —
                usually within 15–60 seconds — this page redirects automatically when the link is ready.
            </p>
        </div>
    @endif
</x-filament-panels::page>
