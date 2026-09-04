<div class="plugsent-apikey" x-data="{ copied: false }">
    <div class="plugsent-apikey-row">
        <code class="plugsent-apikey-code" x-text="revealed ? key : '••••••••••••••••••••••••'"
              x-data="{ key: @js($key), revealed: true }"></code>
        <button type="button" class="plugsent-copy-btn"
                x-on:click="navigator.clipboard.writeText(@js($key)); copied = true; setTimeout(() => copied = false, 1500)"
                x-text="copied ? 'Copied!' : 'Copy'"></button>
    </div>
    <p class="plugsent-apikey-note">
        Paste it into <strong>Settings → Plugsent Connector</strong> on the WordPress site,
        in the <strong>Pairing code</strong> field (the server URL stays the same).
    </p>
</div>
