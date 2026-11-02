<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePrice extends Model
{
    protected $fillable = ['route_id', 'vehicle_type_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
