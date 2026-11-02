<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="trMapPicker({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            zones: @js($getZones()),
            addressStatePath: @js($getAddressStatePath()),
            readOnly: @js($isDisabled()),
        })"
        class="tr-map-wrap"
    >
        @unless ($isDisabled())
            <div class="tr-map-toolbar">
                <input
                    type="search"
                    x-model="query"
                    x-on:keydown.enter.prevent="search()"
                    placeholder="Adres veya otel ara (Enter)…"
                    aria-label="Haritada ara"
                />
                <button type="button" class="tr-map-btn" x-on:click="search()" x-bind:disabled="searching">
                    <span x-show="!searching">Ara</span><span x-show="searching">…</span>
                </button>
                <button type="button" class="tr-map-btn secondary" x-on:click="locateMe()" title="Konumum">📍</button>
            </div>
            <div class="tr-map-results" x-show="results.length" x-on:click.outside="results = []" x-cloak>
                <template x-for="r in results" :key="r.place_id">
                    <button type="button" x-on:click="choose(r)" x-text="r.display_name"></button>
                </template>
            </div>
        @endunless

        <div x-ref="map" class="tr-map" style="height: {{ $getHeight() }}px"></div>

        <div class="tr-map-toolbar" style="border-top: 1px solid var(--gray-200); border-bottom: 0">
            <span class="tr-map-hint">
                @if ($isDisabled())
                    Konum
                @else
                    Haritaya tıklayın veya işaretçiyi sürükleyin. Kesikli alanlar fiyat bölgeleridir.
                @endif
            </span>
            <span class="tr-map-coords" x-show="hasPoint()" x-text="hasPoint() ? `${state.lat}, ${state.lng}` : ''"></span>
        </div>
    </div>
</x-dynamic-component>
