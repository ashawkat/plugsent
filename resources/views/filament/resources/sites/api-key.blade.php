@php
    $connectionString = rtrim(config('app.url'), '/').'::'.$key;
@endphp

<div class="plugsent-connection"
     x-data="{ str: @js($connectionString), copied: false }">
    <div class="plugsent-connection-row">
        <code class="plugsent-connection-code" x-text="str"></code>
        <button type="button" class="plugsent-copy-btn"
                x-on:click="navigator.clipboard.writeText(str); copied = true"
                x-text="copied ? 'Copied!' : 'Copy'"></button>
    </div>
    <p class="plugsent-note">
        Paste this whole string into <strong>Settings → Plugsent Connector</strong> on the WordPress site.
        No expiry — regenerate it from the dashboard any time.
    </p>
    <p class="plugsent-note" style="margin:0 0 4px;">
        Need the plugin? <a href="{{ route('connector.download') }}">Download the connector (.zip) ↗</a>
        — upload via <strong>Plugins → Add New → Upload Plugin</strong> and activate.
    </p>
</div>
