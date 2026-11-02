<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">
            Kesikli alanlar fiyat bölgeleridir; bir adres hangi bölgenin içindeyse o bölgenin rota fiyatını alır.
            Mavi noktalar havalimanı terminalleri, turuncu noktalar kayıtlı lokasyonlardır.
        </x-slot>
        <div wire:ignore x-data="trOverviewMap({ zones: @js($zones), markers: @js($markers) })" class="tr-map-wrap">
            <div x-ref="map" class="tr-map" style="height: calc(100vh - 20rem); min-height: 420px"></div>
        </div>
        <div class="tr-legend">
            @foreach ($legend as $zone)
                <span><span class="dot" style="background: {{ $zone->color }}"></span>{{ $zone->name }}</span>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
