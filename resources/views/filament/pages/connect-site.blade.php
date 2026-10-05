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

        <x-slot:afterHeader>
            <x-filament::actions>
                <x-filament::button
                    tag="a"
                    href="{{ route('connector.download') }}"
                    color="primary"
                    size="md"
                    icon="heroicon-o-arrow-down-tray">
                    Download connector {{ filled($release['tag'] ?? null) ? $release['tag'] : '' }} (.zip)
                </x-filament::button>
                <x-filament::link href="https://github.com/{{ config('plugsent.connector_repo') }}/releases">
                    All releases ↗
                </x-filament::link>
            </x-filament::actions>
        </x-slot:afterHeader>
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
