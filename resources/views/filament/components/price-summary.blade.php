@php
    use App\Filament\Support\FormQuote;
    $currency = config('transfer.currency');
@endphp

<div class="tr-price">
    @if (! $hasInput)
        <div class="tr-empty">
            Alış/bırakış noktası ve tarih girildiğinde fiyat burada otomatik hesaplanır.
        </div>
    @elseif (! $quote || $quote->isEmpty())
        <div class="tr-empty">
            <span class="tr-badge danger">Tarife bulunamadı</span>
            <p style="margin-top:.5rem">Bu güzergah / kişi sayısı için tanımlı fiyat yok. Manuel fiyat girebilirsiniz.</p>
        </div>
    @elseif (! $option)
        <div class="tr-price-row"><span class="tr-price-head">Mesafe</span><span>~{{ round($quote->distanceKm) }} km · {{ $quote->durationMin }} dk</span></div>
        <div class="tr-empty">Fiyat dökümü için araç tipi seçin.<br>
            En uygun: <strong>{{ FormQuote::money($quote->options->first()->total) }}</strong> ({{ $quote->options->first()->vehicleType->name }})
        </div>
    @else
        <div class="tr-price-row">
            <span class="tr-price-head">{{ $option->vehicleType->name }}</span>
            <span>~{{ round($quote->distanceKm) }} km · {{ $quote->durationMin }} dk</span>
        </div>

        @foreach ($option->legs as $leg)
            <div class="tr-price-row">
                <span>
                    {{ $leg->direction->getLabel() }} · {{ $leg->pickupAt->format('d.m H:i') }}
                    <span class="tr-badge {{ $leg->source->value === 'fixed_route' ? 'success' : 'warning' }}">{{ $leg->source->getLabel() }}</span>
                </span>
                <span>{{ FormQuote::money($leg->basePrice) }}</span>
            </div>
            @foreach ($leg->adjustments as $adj)
                <div class="tr-price-row muted">
                    <span>↳ {{ $adj['name'] }}</span>
                    <span>{{ $adj['amount'] >= 0 ? '+' : '−' }}{{ FormQuote::money(abs($adj['amount'])) }}</span>
                </div>
            @endforeach
        @endforeach

        @foreach ($option->extras as $extra)
            <div class="tr-price-row">
                <span>{{ $extra['name'] }} × {{ $extra['quantity'] }}@if ($extra['legs'] > 1) × {{ $extra['legs'] }} yön @endif</span>
                <span>{{ FormQuote::money($extra['total']) }}</span>
            </div>
        @endforeach

        @if ($option->discount > 0)
            <div class="tr-price-row discount">
                <span>Acente indirimi</span>
                <span>−{{ FormQuote::money($option->discount) }}</span>
            </div>
        @endif

        <div class="tr-price-row total">
            <span>Toplam</span>
            <span>{{ FormQuote::money($option->total) }}</span>
        </div>
    @endif
</div>
