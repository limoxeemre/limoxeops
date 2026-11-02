<?php

return [
    // Tüm fiyatların tutulduğu ana para birimi
    'currency' => env('TRANSFER_CURRENCY', 'EUR'),

    // Rezervasyon kodu ön eki: TRF-7KQ2MX
    'code_prefix' => env('TRANSFER_CODE_PREFIX', 'TRF'),

    'pricing' => [
        // Sabit rota bulunamazsa km bazlı fiyat hesaplansın mı?
        'distance_fallback' => env('TRANSFER_DISTANCE_FALLBACK', true),
        // Kuş uçuşu mesafeyi karayolu mesafesine yaklaştırmak için çarpan
        'road_factor' => 1.3,
        // Rota süresi bilinmiyorsa kullanılacak ortalama hız (km/s)
        'average_speed_kmh' => 60,
        // Fiyatlar bu adıma yuvarlanır (ör. 1 => tam sayı, 0.5 => 0.5'lik adım)
        'rounding_step' => 1,
    ],

    'operations' => [
        // Havalimanına gidişlerde uçuştan kaç dk önce havalimanında olunmalı
        'departure_airport_buffer_min' => 150,
        // Gelişlerde iniş saatinden sonra karşılama için eklenecek dk
        'arrival_pickup_delay_min' => 0,
        // Rezervasyonun en erken kaç saat sonrası için alınabileceği
        'min_lead_time_hours' => env('TRANSFER_MIN_LEAD_HOURS', 6),
        // Yolcunun kendisi iptal edebilmesi için transfer saatine kalan min. süre
        'free_cancellation_hours' => 24,
        // Sürücü çakışma kontrolünde kullanılacak ek tampon (dk)
        'driver_conflict_buffer_min' => 30,
    ],
];
