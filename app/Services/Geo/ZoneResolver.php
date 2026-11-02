<?php

namespace App\Services\Geo;

use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Bir koordinatın hangi fiyat bölgesine düştüğünü bulur.
 * Sıralama: priority (büyük önce) → alan (küçük önce). Böylece şehir poligonunun
 * içindeki havalimanı poligonu doğru şekilde eşleşir.
 */
class ZoneResolver
{
    private ?Collection $zones = null;

    public function resolve(float $lat, float $lng): ?Zone
    {
        return $this->zones()->first(fn (Zone $zone) => $zone->contains($lat, $lng));
    }

    public function flush(): void
    {
        $this->zones = null;
    }

    private function zones(): Collection
    {
        return $this->zones ??= Zone::active()->get()->sortBy([
            fn (Zone $a, Zone $b) => $b->priority <=> $a->priority,
            fn (Zone $a, Zone $b) => $this->size($a) <=> $this->size($b),
        ])->values();
    }

    private function size(Zone $zone): float
    {
        if (! empty($zone->polygon)) {
            return GeoMath::area($zone->polygon);
        }

        // Daire alanını derece² cinsine kabaca çevir (1° ≈ 111 km)
        return M_PI * (($zone->radius_km ?? 0) / 111) ** 2;
    }
}
