@php
    $connectionString = rtrim(config('app.url'), '/').'::'.$this->pairingCode;
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
        Expires in 15 minutes and works once. Paste it into
        <strong>Settings → Plugsent Connector</strong> on the WordPress site —
        the site flips to <strong>Connected</strong> on its first check-in.
    </p>
</div>
