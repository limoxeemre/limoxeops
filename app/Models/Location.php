<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Services\Geo\ZoneResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['zone_id', 'name', 'type', 'iata_code', 'address', 'lat', 'lng', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'lat' => 'float',
            'lng' => 'float',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Bölge seçilmediyse (veya konum değiştiyse ve bölge elle değiştirilmediyse) koordinattan bulunur.
        static::saving(function (Location $location) {
            $moved = $location->exists && $location->isDirty(['lat', 'lng']) && ! $location->isDirty('zone_id');
            if ((! $location->zone_id || $moved) && $location->lat !== null && $location->lng !== null) {
                $location->zone_id = app(ZoneResolver::class)->resolve($location->lat, $location->lng)?->id;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function isAirport(): bool
    {
        return $this->type === LocationType::Airport;
    }
}
