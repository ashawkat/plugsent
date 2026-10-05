@php
    $connectionString = rtrim(config('app.url'), '/').'::'.$this->pairingCode;
    $release = \App\Support\ConnectorRelease::latest();
@endphp

<div class="plugsent-connection"
     x-data="{ str: @js($connectionString), copied: false }">
    <p class="plugsent-note" style="margin:0 0 10px;">
        <strong>1.</strong> Install the connector on the WordPress site:<br>
        <a href="{{ route('connector.download') }}" class="plugsent-btn plugsent-btn-primary" style="margin:6px 4px 0 0;">
            Download connector {{ filled($release['tag'] ?? null) ? $release['tag'].' ' : '' }}(.zip)
        </a>
        <a href="https://github.com/{{ config('plugsent.connector_repo') }}/releases" target="_blank" rel="noopener"
           class="plugsent-item-slug">all releases ↗</a>
        <span class="plugsent-note">— upload it via <strong>Plugins → Add New → Upload Plugin</strong> and activate.</span>
    </p>

    <p class="plugsent-note" style="margin:0 0 6px;"><strong>2.</strong> Paste this pairing string into <strong>Settings → Plugsent Connector</strong>:</p>
    <div class="plugsent-connection-row">
        <code class="plugsent-connection-code" x-text="str"></code>
        <button type="button" class="plugsent-copy-btn"
                x-on:click="navigator.clipboard.writeText(str); copied = true"
                x-text="copied ? 'Copied!' : 'Copy'"></button>
    </div>
    <p class="plugsent-note">
        Expires in 15 minutes and works once — the site flips to <strong>Connected</strong> on its first check-in.
    </p>
</div>
