<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationExtra extends Model
{
    protected $fillable = ['reservation_id', 'extra_id', 'quantity', 'unit_price', 'total_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'total_price' => 'decimal:2'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
