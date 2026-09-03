@php
    $serverUrl = rtrim(config('app.url') ?? url('/'), '/');
@endphp

<div class="fi-form space-y-4">
    <div>
        <h3 class="text-sm font-semibold">1. Install the plugin</h3>
        <p class="text-sm opacity-75">
            On your WordPress site, install and activate the <strong>Plugsent Connector</strong> plugin,
            then open <strong>Settings → Plugsent Connector</strong>.
        </p>
    </div>

    <div>
        <h3 class="text-sm font-semibold">2. Paste these two values</h3>
        <p class="text-sm opacity-75">The site reaches your Plugsent server over HTTPS, outbound — no firewall changes needed.</p>
        <div class="mt-2 grid gap-2">
            <div class="flex items-center gap-2">
                <span class="text-xs w-24 opacity-75">Server URL</span>
                <code class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ $serverUrl }}</code>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs w-24 opacity-75">Pairing code</span>
                <code class="rounded bg-gray-100 px-2 py-1 text-sm font-bold dark:bg-gray-800">{{ $this->pairingCode }}</code>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold">3. Wait for the first check-in</h3>
        <p class="text-sm opacity-75">
            The plugin checks in every minute. When it pairs successfully, this site's card flips to
            <strong>Connected</strong> and its plugin &amp; theme inventory appears. Expires at {{ $this->expiresAt }}.
        </p>
    </div>
</div>
