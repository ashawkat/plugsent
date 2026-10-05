<x-filament-panels::page>
    @php $release = \App\Support\ConnectorRelease::latest(); @endphp

    <x-filament::section>
        <x-slot:heading>
            1. Install the connector plugin
        </x-slot:heading>
        <x-slot:description>
            Download the connector, upload it on the WordPress site via
            <strong>Plugins → Add New → Upload Plugin</strong>, and activate it. Then generate a pairing code below.
        </x-slot:description>

        <x-slot:actions>
            <a href="{{ route('connector.download') }}"
               class="fi-btn fi-btn-color-primary fi-btn-size-md fi-accent-action">
                Download connector {{ filled($release['tag'] ?? null) ? $release['tag'] : '' }} (.zip)
            </a>
            <a href="https://github.com/{{ config('plugsent.connector_repo') }}/releases" target="_blank" rel="noopener"
               class="fi-link fi-link-size-md">
                All releases ↗
            </a>
        </x-slot:actions>
    </x-filament::section>

    {{ $this->content }}

    @if(filled($this->pairingCode))
        <x-filament::section>
            <x-slot:heading>
                2. Finish pairing on the WordPress site
            </x-slot:heading>
            <x-slot:description>
                Valid for 15 minutes, single use. The site flips to Connected automatically on its first check-in.
            </x-slot:description>

            @include('filament.pages.connect-site-instructions')
        </x-filament::section>
    @endif
</x-filament-panels::page>
