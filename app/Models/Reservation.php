<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TransferDirection;
use App\Enums\TripType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'agency_id', 'agency_reference', 'vehicle_type_id', 'status', 'source', 'trip_type',
        'passenger_name', 'passenger_phone', 'passenger_email', 'passenger_country',
        'adults', 'children', 'infants', 'luggage',
        'subtotal', 'discount', 'total_price', 'currency', 'price_breakdown', 'payment_status', 'payment_method',
        'customer_notes', 'internal_notes', 'confirmed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'source' => BookingSource::class,
            'trip_type' => TripType::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'price_breakdown' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        // Karışabilecek karakterler (0/O, 1/I) çıkarıldı; telefonda okunması kolay olsun.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = config('transfer.code_prefix').'-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class)->orderBy('pickup_at');
    }

    public function outbound(): HasOne
    {
        return $this->hasOne(Transfer::class)->where('direction', TransferDirection::Outbound);
    }

    public function return(): HasOne
    {
        return $this->hasOne(Transfer::class)->where('direction', TransferDirection::Return);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(ReservationExtra::class);
    }

    public function getTotalPassengersAttribute(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function getWhatsappUrlAttribute(): string
    {
        return 'https://wa.me/'.Str::of($this->passenger_phone)->replaceMatches('/\D+/', '');
    }
}
