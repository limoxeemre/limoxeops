<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'max_passengers', 'max_luggage',
        'base_fare', 'per_km_rate', 'min_fare', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'per_km_rate' => 'decimal:2',
            'min_fare' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFitting(Builder $query, int $passengers, int $luggage = 0): Builder
    {
        return $query->where('max_passengers', '>=', $passengers)->where('max_luggage', '>=', $luggage);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function routePrices(): HasMany
    {
        return $this->hasMany(RoutePrice::class);
    }

    public function hasDistanceTariff(): bool
    {
        return (float) $this->per_km_rate > 0;
    }
}
