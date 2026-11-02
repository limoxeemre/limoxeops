<?php

namespace App\Filament\Forms\Components;

use App\Models\Zone;
use Closure;
use Filament\Forms\Components\Field;

/**
 * Bölge sınırını haritada poligon olarak çizme/düzenleme. State: [[lat, lng], ...]
 */
class ZonePolygonEditor extends Field
{
    protected string $view = 'filament.forms.zone-polygon-editor';

    protected string|Closure $color = '#3b82f6';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
    }

    public function color(string|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->evaluate($this->color) ?: '#3b82f6';
    }

    /** Düzenlenen bölge hariç diğer bölgeler (referans olarak soluk gösterilir). */
    public function getOtherZones(): array
    {
        return static::zonesForMap($this->getRecord()?->getKey());
    }

    public static function zonesForMap(?int $exceptId = null): array
    {
        return Zone::query()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->map(fn (Zone $z) => [
                'id' => $z->id,
                'name' => $z->name,
                'type_label' => $z->type?->getLabel(),
                'color' => $z->color,
                'polygon' => $z->polygon,
                'center_lat' => $z->center_lat,
                'center_lng' => $z->center_lng,
                'radius_km' => $z->radius_km,
            ])
            ->all();
    }
}
