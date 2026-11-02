<?php

namespace App\Services\Geo;

use App\Enums\LocationType;
use App\Enums\ZoneType;
use App\Models\Location;
use App\Models\Zone;
use InvalidArgumentException;

/**
 * Alış / bırakış noktası. Ya kayıtlı bir lokasyondan (otel, terminal) ya da
 * serbest koordinattan (Google Places, harita seçimi) oluşturulur.
 */
final class Waypoint
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $address,
        public readonly ?Location $location = null,
        public readonly ?Zone $zone = null,
    ) {}

    /**
     * @param  array{location_id?: int|null, lat?: float|null, lng?: float|null, address?: string|null}  $data
     */
    public static function fromArray(array $data, ?ZoneResolver $resolver = null): self
    {
        $resolver ??= app(ZoneResolver::class);

        if (! empty($data['location_id'])) {
            $location = Location::with('zone')->findOrFail($data['location_id']);

            return new self(
                lat: $location->lat,
                lng: $location->lng,
                address: $data['address'] ?? ($location->address ?: $location->name),
                location: $location,
                zone: $location->zone ?? $resolver->resolve($location->lat, $location->lng),
            );
        }

        if (! isset($data['lat'], $data['lng'])) {
            throw new InvalidArgumentException('Waypoint requires either location_id or lat/lng.');
        }

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        return new self(
            lat: $lat,
            lng: $lng,
            address: $data['address'] ?? sprintf('%.6f, %.6f', $lat, $lng),
            zone: $resolver->resolve($lat, $lng),
        );
    }

    public function isAirport(): bool
    {
        return $this->location?->type === LocationType::Airport || $this->zone?->type === ZoneType::Airport;
    }

    public function distanceTo(self $other): float
    {
        return GeoMath::haversineKm($this->lat, $this->lng, $other->lat, $other->lng);
    }

    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'location_id' => $this->location?->id,
            'location_name' => $this->location?->name,
            'zone_id' => $this->zone?->id,
            'zone_name' => $this->zone?->name,
            'is_airport' => $this->isAirport(),
        ];
    }
}
