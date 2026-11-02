@php
    use App\Filament\Support\FormQuote;
    $cur = $r->currency;
    $legs = $r->price_breakdown['legs'] ?? [];
@endphp
<div class="tr-price">
    @forelse ($r->transfers as $transfer)
        @php $leg = collect($legs)->firstWhere('direction', $transfer->direction->value); @endphp
        <div class="tr-price-row">
            <span>{{ $transfer->direction->getLabel() }} · {{ $transfer->pickup_at->format('d.m H:i') }}
                <span class="tr-badge {{ $transfer->price_source->value === 'fixed_route' ? 'success' : 'warning' }}">{{ $transfer->price_source->getLabel() }}</span>
            </span>
            <span>{{ FormQuote::money($transfer->price, $cur) }}</span>
        </div>
        @foreach ($leg['adjustments'] ?? [] as $adj)
            <div class="tr-price-row muted"><span>↳ {{ $adj['name'] }}</span><span>{{ $adj['amount'] >= 0 ? '+' : '−' }}{{ FormQuote::money(abs($adj['amount']), $cur) }}</span></div>
        @endforeach
    @empty
    @endforelse
    @foreach ($r->extras as $line)
        <div class="tr-price-row"><span>{{ $line->extra->name }} × {{ $line->quantity }}</span><span>{{ FormQuote::money($line->total_price, $cur) }}</span></div>
    @endforeach
    @if ((float) $r->discount > 0)
        <div class="tr-price-row discount"><span>Acente indirimi</span><span>−{{ FormQuote::money($r->discount, $cur) }}</span></div>
    @endif
    <div class="tr-price-row total"><span>Toplam</span><span>{{ FormQuote::money($r->total_price, $cur) }}</span></div>
</div>
