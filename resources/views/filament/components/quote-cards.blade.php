@php
    use App\Filament\Resources\Reservations\ReservationResource;
    use App\Filament\Support\FormQuote;

    $prefillKeys = ['pickup_location_id', 'pickup_address', 'pickup_lat', 'pickup_lng', 'dropoff_location_id', 'dropoff_address',
        'dropoff_lat', 'dropoff_lng', 'pickup_at', 'flight_time', 'round_trip', 'return_pickup_at', 'return_flight_time',
        'adults', 'children', 'infants', 'luggage', 'agency_id'];
@endphp

@if (! $payload)
    <div class="tr-empty">Nereden / nereye ve tarih bilgisi girildiğinde tüm araç seçenekleri burada listelenir.</div>
@elseif (! $quote || $quote->isEmpty())
    <div class="tr-empty"><span class="tr-badge danger">Tarife bulunamadı</span>
        <p style="margin-top:.5rem">Bu güzergah veya kişi/bagaj sayısı için fiyat tanımlı değil. Rota ekleyin ya da manuel fiyatla rezervasyon oluşturun.</p>
    </div>
@else
    <div class="tr-quote-meta">
        <span class="tr-badge info">{{ $quote->request->pickup->zone?->name ?? 'Bölge dışı' }} → {{ $quote->request->dropoff->zone?->name ?? 'Bölge dışı' }}</span>
        <span class="tr-badge info">~{{ round($quote->distanceKm) }} km · {{ $quote->durationMin }} dk</span>
        <span class="tr-badge info">{{ $quote->request->passengers() }} kişi · {{ $quote->request->luggage }} bagaj</span>
        @if ($quote->request->isRoundTrip()) <span class="tr-badge info">Gidiş - dönüş</span> @endif
        @if ($quote->request->agency) <span class="tr-badge success">{{ $quote->request->agency->name }} · %{{ (float) $quote->request->agency->discount_percent }} indirim</span> @endif
    </div>

    <div class="tr-quote-grid">
        @foreach ($quote->options as $option)
            @php
                $prefill = collect($state)->only($prefillKeys)->filter(fn ($v) => $v !== null && $v !== '')->all();
                $prefill['vehicle_type_id'] = $option->vehicleType->id;
                if (! empty($prefill['agency_id'])) { $prefill['source'] = 'agency'; }
                $url = ReservationResource::getUrl('create', ['prefill' => base64_encode(json_encode($prefill))]);
            @endphp
            <div class="tr-quote-card">
                <h3>{{ $option->vehicleType->name }}</h3>
                <div class="cap">👤 {{ $option->vehicleType->max_passengers }} · 🧳 {{ $option->vehicleType->max_luggage }} · {{ $option->vehicleType->description }}</div>
                <div class="price">{{ FormQuote::money($option->total) }}</div>
                @foreach ($option->legs as $leg)
                    <div class="tr-price-row muted" style="padding-left:0">
                        <span>{{ $leg->direction->getLabel() }} <span class="tr-badge {{ $leg->source->value === 'fixed_route' ? 'success' : 'warning' }}">{{ $leg->source->getLabel() }}</span></span>
                        <span>{{ FormQuote::money($leg->price) }}</span>
                    </div>
                    @foreach ($leg->adjustments as $adj)
                        <div class="tr-price-row muted"><span>↳ {{ $adj['name'] }}</span><span>{{ $adj['amount'] >= 0 ? '+' : '−' }}{{ FormQuote::money(abs($adj['amount'])) }}</span></div>
                    @endforeach
                @endforeach
                @if ($option->discount > 0)
                    <div class="tr-price-row discount" style="font-size:.8125rem"><span>İndirim</span><span>−{{ FormQuote::money($option->discount) }}</span></div>
                @endif
                <div style="margin-top:.75rem">
                    <x-filament::button tag="a" :href="$url" icon="heroicon-m-arrow-right" icon-position="after" size="sm" style="width:100%">
                        Rezervasyon oluştur
                    </x-filament::button>
                </div>
            </div>
        @endforeach
    </div>
@endif
