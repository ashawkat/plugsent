<x-filament-panels::page>
    {{ $this->content }}

    @if(filled($this->pairingCode))
        <x-filament::section>
            <x-slot:heading>
                Finish pairing on the WordPress site
            </x-slot:heading>
            <x-slot:description>
                Valid for 15 minutes, single use. The site flips to Connected automatically on its first check-in.
            </x-slot:description>

            @include('filament.pages.connect-site-instructions')
        </x-filament::section>
    @endif
</x-filament-panels::page>
