<x-filament-panels::page wire:poll.2s="checkForLoginUrl">
    <div class="plugsent-process plugsent-connecting">
        <div class="plugsent-process-head">
            <span class="plugsent-process-spinner">⟳</span>
            <strong>Opening WordPress admin for {{ $this->site->name }}…</strong>
        </div>
        <p class="plugsent-process-note">
            The site is generating a one-time login link. You'll be redirected automatically —
            usually within a few seconds.
        </p>
    </div>
</x-filament-panels::page>
