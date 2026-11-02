<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['from_zone_id', 'to_zone_id', 'is_bidirectional', 'distance_km', 'duration_min', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_bidirectional' => 'boolean',
            'is_active' => 'boolean',
            'distance_km' => 'float',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * İki bölge arasındaki rotayı bulur. Önce birebir yön, yoksa çift yönlü ters rota.
     */
    public function scopeBetween(Builder $query, int $fromZoneId, int $toZoneId): Builder
    {
        return $query->where(function (Builder $q) use ($fromZoneId, $toZoneId) {
            $q->where(fn ($q) => $q->where('from_zone_id', $fromZoneId)->where('to_zone_id', $toZoneId))
                ->orWhere(fn ($q) => $q->where('from_zone_id', $toZoneId)->where('to_zone_id', $fromZoneId)->where('is_bidirectional', true));
        })->orderByRaw('CASE WHEN from_zone_id = ? THEN 0 ELSE 1 END', [$fromZoneId]);
    }

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'to_zone_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RoutePrice::class);
    }

    public function getLabelAttribute(): string
    {
        $arrow = $this->is_bidirectional ? ' ⇄ ' : ' → ';

        return $this->fromZone?->name.$arrow.$this->toZone?->name;
    }
}
