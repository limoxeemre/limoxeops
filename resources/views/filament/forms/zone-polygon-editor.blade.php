<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="trZoneEditor({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            zones: @js($getOtherZones()),
            color: @js($getColor()),
        })"
        class="tr-map-wrap"
    >
        <div x-ref="map" class="tr-map" style="height: 480px"></div>
        <div class="tr-map-toolbar" style="border-top: 1px solid var(--gray-200); border-bottom: 0">
            <span class="tr-map-hint">
                Sol üstteki araçlarla <strong>poligon</strong> veya <strong>dikdörtgen</strong> çizin, köşeleri sürükleyerek düzenleyin.
                Diğer bölgeler kesikli çizgiyle gösterilir.
            </span>
            <span class="tr-map-coords" x-text="pointCount ? pointCount + ' köşe' : 'Sınır çizilmedi'"></span>
            <button type="button" class="tr-map-btn secondary" x-on:click="clear()" x-show="pointCount">Temizle</button>
        </div>
    </div>
</x-dynamic-component>
