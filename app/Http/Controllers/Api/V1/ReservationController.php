<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Agency;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service) {}

    /** POST /reservations – fiyat sunucuda yeniden hesaplanır. */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $agency = $request->user('sanctum') instanceof Agency ? $request->user('sanctum') : null;
        abort_if($agency && ! $agency->is_active, 403, 'Acente hesabı pasif.');

        $reservation = $this->service->create(
            $request->validated(),
            $agency ? BookingSource::Agency : BookingSource::Website,
            $agency,
            $agency,
        );

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    /** GET /reservations/{code}?email=... – yolcu kendi rezervasyonunu görüntüler. */
    public function show(Request $request, string $code): ReservationResource
    {
        return new ReservationResource($this->findForRequester($request, $code));
    }

    /** POST /reservations/{code}/cancel */
    public function cancel(Request $request, string $code): ReservationResource
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $reservation = $this->findForRequester($request, $code);
        $agency = $request->user('sanctum') instanceof Agency;

        abort_unless(
            $this->service->isCancellableByCustomer($reservation),
            422,
            'Bu rezervasyon online iptal edilemez (ücretsiz iptal süresi geçmiş veya transfer başlamış). Lütfen bizimle iletişime geçin.',
        );

        $reservation = $this->service->cancel(
            $reservation,
            $request->input('reason', $agency ? 'Acente tarafından iptal' : 'Yolcu tarafından iptal'),
            $request->user('sanctum'),
        );

        return new ReservationResource($reservation->load('transfers', 'vehicleType', 'extras.extra'));
    }

    /** GET /agency/reservations – acentenin kendi rezervasyonları */
    public function agencyIndex(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        $reservations = $request->user()->reservations()
            ->with('transfers', 'vehicleType', 'extras.extra')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereHas('transfers', fn ($q) => $q->where('pickup_at', '>=', $d)))
            ->when($request->to, fn ($q, $d) => $q->whereHas('transfers', fn ($q) => $q->where('pickup_at', '<=', $d.' 23:59:59')))
            ->latest()
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ReservationResource::collection($reservations);
    }

    private function findForRequester(Request $request, string $code): Reservation
    {
        $query = Reservation::with('transfers.driver', 'transfers.vehicle', 'vehicleType', 'extras.extra')
            ->where('code', strtoupper($code));

        $user = $request->user('sanctum');
        if ($user instanceof Agency) {
            $query->where('agency_id', $user->id);
        } else {
            // Tahmin edilebilir kodlara karşı: e-posta eşleşmesi zorunlu
            $request->validate(['email' => ['required', 'email']]);
            $query->where('passenger_email', $request->input('email'));
        }

        return $query->firstOrFail();
    }
}
