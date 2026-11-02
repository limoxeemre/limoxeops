<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Str;

/**
 * Harita üzerinden koordinat seçimi. State: ['lat' => float, 'lng' => float]
 *
 * MapPicker::make('pickup_point')->latLngFields('pickup_lat', 'pickup_lng')->addressField('pickup_address')
 */
class MapPicker extends Field
{
    protected string $view = 'filament.forms.map-picker';

    protected int|Closure $height = 320;

    protected ?string $addressField = null;

    protected bool $showZones = true;

    protected ?array $latLngFields = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['lat' => null, 'lng' => null]);
        $this->live();

        // lat/lng ayrı kolonlarda tutuluyorsa: doldururken oradan oku, değişince oraya yaz
        $this->afterStateHydrated(function (MapPicker $component, $get) {
            if ($fields = $component->getLatLngFields()) {
                $component->state(['lat' => $get($fields[0]), 'lng' => $get($fields[1])]);
            }
        });

        $this->afterStateUpdated(function (MapPicker $component, $state, $set) {
            if ($fields = $component->getLatLngFields()) {
                $set($fields[0], $state['lat'] ?? null);
                $set($fields[1], $state['lng'] ?? null);
            }
        });
    }

    public function latLngFields(string $lat, string $lng): static
    {
        $this->latLngFields = [$lat, $lng];
        $this->dehydrated(false);

        return $this;
    }

    public function getLatLngFields(): ?array
    {
        return $this->latLngFields;
    }

    public function height(int|Closure $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): int
    {
        return (int) $this->evaluate($this->height);
    }

    /** Haritada nokta seçilince ters geocoding ile doldurulacak kardeş alan. */
    public function addressField(?string $name): static
    {
        $this->addressField = $name;

        return $this;
    }

    public function getAddressStatePath(): ?string
    {
        if (! $this->addressField) {
            return null;
        }

        return Str::beforeLast($this->getStatePath(), '.').'.'.$this->addressField;
    }

    public function showZones(bool $condition = true): static
    {
        $this->showZones = $condition;

        return $this;
    }

    public function getZones(): array
    {
        return $this->showZones ? ZonePolygonEditor::zonesForMap() : [];
    }
}
