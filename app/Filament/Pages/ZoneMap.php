<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\ZonePolygonEditor;
use App\Models\Location;
use App\Models\Zone;
use Filament\Pages\Page;

class ZoneMap extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Bölge Haritası';

    protected static ?string $title = 'Bölge Haritası';

    protected string $view = 'filament.pages.zone-map';

    protected function getViewData(): array
    {
        return [
            'zones' => ZonePolygonEditor::zonesForMap(),
            'legend' => Zone::active()->orderByDesc('priority')->orderBy('name')->get(['name', 'color', 'type']),
            'markers' => Location::active()->get()->map(fn (Location $l) => [
                'lat' => $l->lat, 'lng' => $l->lng, 'label' => $l->name.' ('.$l->type->getLabel().')',
                'color' => $l->isAirport() ? '#0284c7' : '#f97316', 'size' => $l->isAirport() ? 8 : 5,
            ])->all(),
        ];
    }
}
