<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Enums\TransferStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    /** Sürücünün bu transferle çakışan diğer işleri. */
    public function conflictsFor(Transfer $transfer, Driver $driver): Collection
    {
        return $driver->conflictingTransfers($transfer->pickup_at, $transfer->estimatedEndAt(), $transfer->id);
    }

    public function assign(Transfer $transfer, Driver $driver, ?Vehicle $vehicle = null, ?Model $causer = null): Transfer
    {
        if (! $transfer->status->isActive()) {
            throw ValidationException::withMessages(['status' => 'Tamamlanmış/iptal edilmiş transfere atama yapılamaz.']);
        }

        $vehicle ??= $driver->vehicle;

        if ($vehicle && $vehicle->vehicleType->max_passengers < $transfer->reservation->total_passengers) {
            throw ValidationException::withMessages([
                'vehicle_id' => "{$vehicle->plate} kapasitesi ({$vehicle->vehicleType->max_passengers}) yolcu sayısı için yetersiz.",
            ]);
        }

        return DB::transaction(function () use ($transfer, $driver, $vehicle, $causer) {
            $from = $transfer->status;
            $transfer->fill([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'assigned_at' => now(),
            ]);

            if ($from === TransferStatus::Pending) {
                $transfer->status = TransferStatus::Assigned;
            }
            $transfer->save();

            $this->log($transfer, $from, $transfer->status, $causer, "Sürücü: {$driver->name}".($vehicle ? " ({$vehicle->plate})" : ''));

            return $transfer;
        });
    }

    public function unassign(Transfer $transfer, ?Model $causer = null): Transfer
    {
        $from = $transfer->status;
        $transfer->update(['driver_id' => null, 'vehicle_id' => null, 'assigned_at' => null, 'status' => TransferStatus::Pending]);
        $this->log($transfer, $from, TransferStatus::Pending, $causer, 'Sürücü ataması kaldırıldı');

        return $transfer;
    }

    public function changeStatus(
        Transfer $transfer,
        TransferStatus $to,
        ?Model $causer = null,
        ?string $note = null,
        ?float $lat = null,
        ?float $lng = null,
        bool $force = false,
    ): Transfer {
        $from = $transfer->status;

        if (! $force && ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "\"{$from->getLabel()}\" durumundan \"{$to->getLabel()}\" durumuna geçilemez.",
            ]);
        }

        if ($to === TransferStatus::Assigned && ! $transfer->driver_id) {
            throw ValidationException::withMessages(['status' => 'Önce sürücü atanmalıdır.']);
        }

        DB::transaction(function () use ($transfer, $from, $to, $causer, $note, $lat, $lng) {
            $timestamps = match ($to) {
                TransferStatus::EnRoute => ['started_at' => now()],
                TransferStatus::OnBoard => ['picked_up_at' => now()],
                TransferStatus::Completed, TransferStatus::NoShow => ['completed_at' => now()],
                default => [],
            };

            $transfer->update(['status' => $to, ...$timestamps]);
            $this->log($transfer, $from, $to, $causer, $note, $lat, $lng);
            $this->syncReservationStatus($transfer);
        });

        return $transfer;
    }

    /** Tüm bacaklar kapandıysa rezervasyonu "tamamlandı" yap. */
    private function syncReservationStatus(Transfer $transfer): void
    {
        $reservation = $transfer->reservation;
        if ($reservation->status === ReservationStatus::Cancelled) {
            return;
        }

        $legs = $reservation->transfers()->get();
        if ($legs->every(fn (Transfer $t) => ! $t->status->isActive()) && $legs->contains(fn (Transfer $t) => $t->status === TransferStatus::Completed)) {
            $reservation->update(['status' => ReservationStatus::Completed]);
        }
    }

    private function log(Transfer $transfer, ?TransferStatus $from, TransferStatus $to, ?Model $causer, ?string $note = null, ?float $lat = null, ?float $lng = null): void
    {
        $transfer->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'note' => $note,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }
}
