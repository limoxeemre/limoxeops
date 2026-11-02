<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteFormRequest;
use App\Models\Agency;
use App\Services\Pricing\PriceCalculator;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    /**
     * POST /quotes
     * Güzergah + tarih + kişi sayısına göre uygun tüm araç tipleri için fiyat döner.
     * Acente token'ı ile çağrılırsa acente indirimi uygulanır.
     */
    public function __invoke(QuoteFormRequest $request, ReservationService $reservations, PriceCalculator $calculator): JsonResponse
    {
        $agency = $request->user('sanctum') instanceof Agency ? $request->user('sanctum') : null;

        $quote = $calculator->quote($reservations->buildQuoteRequest($request->validated(), $agency));

        return response()->json([
            'data' => $quote->toArray(),
            'meta' => [
                'available' => ! $quote->isEmpty(),
                'message' => $quote->isEmpty()
                    ? 'Bu güzergah veya kişi sayısı için online fiyat bulunamadı. Lütfen bizimle iletişime geçin.'
                    : null,
            ],
        ]);
    }
}
