<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'name', 'adjustment_type', 'value', 'starts_on', 'ends_on', 'time_from', 'time_to',
        'days_of_week', 'vehicle_type_id', 'zone_id', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_type' => AdjustmentType::class,
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Kural verilen transfer bacağına uygulanır mı?
     */
    public function appliesTo(CarbonInterface $pickupAt, int $vehicleTypeId, array $zoneIds = []): bool
    {
        if ($this->vehicle_type_id && $this->vehicle_type_id !== $vehicleTypeId) {
            return false;
        }

        if ($this->zone_id && ! in_array($this->zone_id, $zoneIds, true)) {
            return false;
        }

        $date = $pickupAt->toDateString();
        if ($this->starts_on && $date < $this->starts_on->toDateString()) {
            return false;
        }
        if ($this->ends_on && $date > $this->ends_on->toDateString()) {
            return false;
        }

        if (! empty($this->days_of_week) && ! in_array($pickupAt->isoWeekday(), array_map('intval', $this->days_of_week), true)) {
            return false;
        }

        if ($this->time_from && $this->time_to) {
            $time = $pickupAt->format('H:i:s');
            $from = substr($this->time_from, 0, 8);
            $to = substr($this->time_to, 0, 8);

            $inWindow = $from <= $to
                ? ($time >= $from && $time < $to)
                : ($time >= $from || $time < $to); // gece yarısını geçen aralık (22:00 - 06:00)

            if (! $inWindow) {
                return false;
            }
        }

        return true;
    }

    public function adjustmentFor(float $basePrice): float
    {
        return $this->adjustment_type === AdjustmentType::Percent
            ? round($basePrice * (float) $this->value / 100, 2)
            : (float) $this->value;
    }

    public function getDescriptionAttribute(): string
    {
        $value = (float) $this->value;
        $sign = $value >= 0 ? '+' : '−';
        $abs = abs($value);

        return $this->adjustment_type === AdjustmentType::Percent
            ? "{$sign}%{$abs}"
            : "{$sign}{$abs} ".config('transfer.currency');
    }
}
