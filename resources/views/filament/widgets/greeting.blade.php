<div class="plugsent-dash-greet">
    <div class="plugsent-dash-greet-copy">
        <h2 class="plugsent-dash-greet-title">{{ $greeting }}, {{ $name }}</h2>
        <p class="plugsent-dash-greet-sub">{{ $sub }}</p>
    </div>
    <div class="plugsent-dash-greet-actions">
        @if ($canRefreshAll)
            <button type="button" class="plugsent-btn plugsent-dash-btn" wire:click="refreshAll" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshAll">↻ Refresh all</span>
                <span wire:loading wire:target="refreshAll">Queuing…</span>
            </button>
        @endif
        <a href="{{ $connectUrl }}" class="plugsent-btn plugsent-btn-primary plugsent-dash-btn">＋ Connect site</a>
    </div>
</div>
