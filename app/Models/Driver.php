<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'license_number', 'languages', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    /**
     * Sürücünün verilen zaman aralığında çakışan aktif transferleri.
     */
    public function conflictingTransfers(CarbonInterface $start, CarbonInterface $end, ?int $ignoreTransferId = null)
    {
        $buffer = (int) config('transfer.operations.driver_conflict_buffer_min');

        return $this->transfers()
            ->whereIn('status', TransferStatus::activeValues())
            ->when($ignoreTransferId, fn ($q) => $q->whereKeyNot($ignoreTransferId))
            // Kaba ön filtre (en uzun transfer ~6 saat), kesin kontrol PHP tarafında
            ->whereBetween('pickup_at', [$start->copy()->subHours(6), $end->copy()->addMinutes($buffer)])
            ->get()
            ->filter(fn (Transfer $t) => $t->pickup_at->lt($end->copy()->addMinutes($buffer))
                && $t->estimatedEndAt()->addMinutes($buffer)->gt($start));
    }
}
