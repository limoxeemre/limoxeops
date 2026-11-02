<?php

namespace App\Models;

use App\Enums\ZoneType;
use App\Services\Geo\GeoMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'color', 'polygon', 'center_lat', 'center_lng', 'radius_km', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ZoneType::class,
            'polygon' => 'array',
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius_km' => 'float',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Poligon kaydedilince merkez noktası otomatik hesaplanır (harita & mesafe için).
        static::saving(function (Zone $zone) {
            if (! empty($zone->polygon) && $zone->isDirty('polygon')) {
                [$zone->center_lat, $zone->center_lng] = GeoMath::centroid($zone->polygon);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function outgoingRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'from_zone_id');
    }

    public function incomingRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'to_zone_id');
    }

    public function contains(float $lat, float $lng): bool
    {
        if (! empty($this->polygon) && count($this->polygon) >= 3) {
            return GeoMath::pointInPolygon($lat, $lng, $this->polygon);
        }

        if ($this->radius_km && $this->center_lat !== null && $this->center_lng !== null) {
            return GeoMath::haversineKm($lat, $lng, $this->center_lat, $this->center_lng) <= $this->radius_km;
        }

        return false;
    }
}
