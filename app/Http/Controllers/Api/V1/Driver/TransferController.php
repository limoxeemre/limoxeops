<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\Reservations\TransferService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sürücü mobil uygulaması uçları. Token panelden "API Token Oluştur" ile verilir.
 */
class TransferController extends Controller
{
    /** GET /driver/transfers?date=2026-11-03 */
    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $date = CarbonImmutable::parse($request->input('date', today()));

        $transfers = $request->user()->transfers()
            ->with('reservation.extras.extra', 'pickupZone', 'dropoffZone', 'vehicle')
            ->onDate($date)
            ->orderBy('pickup_at')
            ->get();

        return TransferResource::collection($transfers);
    }

    public function show(Request $request, Transfer $transfer): TransferResource
    {
        abort_unless($transfer->driver_id === $request->user()->id, 404);

        return new TransferResource($transfer->load('reservation.extras.extra', 'pickupZone', 'dropoffZone', 'vehicle'));
    }

    /** PATCH /driver/transfers/{id}/status  {status: en_route|on_board|completed|no_show, lat?, lng?, note?} */
    public function updateStatus(Request $request, Transfer $transfer, TransferService $service): TransferResource
    {
        abort_unless($transfer->driver_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                TransferStatus::EnRoute->value, TransferStatus::OnBoard->value,
                TransferStatus::Completed->value, TransferStatus::NoShow->value,
            ])],
            'note' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $service->changeStatus(
            $transfer,
            TransferStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return new TransferResource($transfer->refresh()->load('reservation.extras.extra', 'pickupZone', 'dropoffZone', 'vehicle'));
    }
}
