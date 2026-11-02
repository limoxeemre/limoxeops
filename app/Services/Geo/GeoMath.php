<?php

namespace App\Services\Geo;

final class GeoMath
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Ray-casting algoritması. Poligon [[lat, lng], ...] biçimindedir.
     * Şehir ölçeğindeki bölgeler için düzlemsel yaklaşım yeterince hassastır
     * ve veritabanından bağımsız çalışır (SQLite/MySQL/Postgres).
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = [(float) $polygon[$i][0], (float) $polygon[$i][1]];
            [$latJ, $lngJ] = [(float) $polygon[$j][0], (float) $polygon[$j][1]];

            $intersects = (($latI > $lat) !== ($latJ > $lat))
                && ($lng < ($lngJ - $lngI) * ($lat - $latI) / (($latJ - $latI) ?: 1e-12) + $lngI);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @return array{0: float, 1: float} */
    public static function centroid(array $polygon): array
    {
        $lats = array_map(fn ($p) => (float) $p[0], $polygon);
        $lngs = array_map(fn ($p) => (float) $p[1], $polygon);

        return [round(array_sum($lats) / count($lats), 7), round(array_sum($lngs) / count($lngs), 7)];
    }

    /** Yaklaşık alan (derece²) – çakışan bölgelerde küçük olanı öne almak için. */
    public static function area(array $polygon): float
    {
        $sum = 0.0;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $sum += ($polygon[$j][1] + $polygon[$i][1]) * ($polygon[$j][0] - $polygon[$i][0]);
        }

        return abs($sum / 2);
    }
}
