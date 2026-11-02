<x-mail::message>
# Rezervasyonunuz alındı

Merhaba {{ $reservation->passenger_name }},

**{{ $reservation->code }}** numaralı transfer rezervasyonunuzun detayları aşağıdadır.

@foreach ($reservation->transfers as $transfer)
<x-mail::panel>
**{{ $transfer->direction->getLabel() }}** — {{ $transfer->pickup_at->translatedFormat('d F Y, l H:i') }}

📍 {{ $transfer->pickup_address }}<br>
🏁 {{ $transfer->dropoff_address }}
@if ($transfer->flight_number)
<br>✈️ Uçuş: {{ $transfer->flight_number }}
@endif
</x-mail::panel>
@endforeach

<x-mail::table>
| | |
|:--|--:|
| Araç | {{ $reservation->vehicleType->name }} |
| Yolcu | {{ $reservation->adults }} yetişkin, {{ $reservation->children }} çocuk, {{ $reservation->infants }} bebek |
@foreach ($reservation->extras as $line)
| {{ $line->extra->name }} × {{ $line->quantity }} | {{ number_format($line->total_price, 2) }} {{ $reservation->currency }} |
@endforeach
| **Toplam** | **{{ number_format($reservation->total_price, 2) }} {{ $reservation->currency }}** |
</x-mail::table>

Havalimanı çıkışında sürücümüz isminizin yazılı olduğu tabela ile sizi karşılayacaktır.

İyi yolculuklar,<br>
{{ config('app.name') }}
</x-mail::message>
