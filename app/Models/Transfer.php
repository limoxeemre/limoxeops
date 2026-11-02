<?php

namespace App\Models;

use App\Enums\PriceSource;
use App\Enums\TransferDirection;
use App\Enums\TransferStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id', 'direction', 'status', 'pickup_at', 'flight_number', 'flight_time',
        'pickup_location_id', 'pickup_zone_id', 'pickup_address', 'pickup_lat', 'pickup_lng',
        'dropoff_location_id', 'dropoff_zone_id', 'dropoff_address', 'dropoff_lat', 'dropoff_lng',
        'route_id', 'price_source', 'distance_km', 'duration_min', 'price',
        'driver_id', 'vehicle_id', 'driver_notes', 'assigned_at', 'started_at', 'picked_up_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'status' => TransferStatus::class,
            'price_source' => PriceSource::class,
            'pickup_at' => 'datetime',
            'flight_time' => 'datetime',
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'distance_km' => 'float',
            'price' => 'decimal:2',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TransferStatus::activeValues());
    }

    public function scopeOnDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereBetween('pickup_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function pickupZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'pickup_zone_id');
    }

    public function dropoffZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'dropoff_zone_id');
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dropoffLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TransferStatusLog::class)->latest('id');
    }

    public function estimatedEndAt(): CarbonInterface
    {
        $minutes = $this->duration_min
            ?: (int) ceil(($this->distance_km ?: 30) / config('transfer.pricing.average_speed_kmh') * 60);

        return $this->pickup_at->copy()->addMinutes($minutes);
    }

    public function getGoogleMapsUrlAttribute(): string
    {
        return sprintf(
            'https://www.google.com/maps/dir/?api=1&origin=%s,%s&destination=%s,%s',
            $this->pickup_lat, $this->pickup_lng, $this->dropoff_lat, $this->dropoff_lng,
        );
    }
}
