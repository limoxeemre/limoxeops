<?php

namespace Tests\Unit;

use App\Services\Geo\GeoMath;
use PHPUnit\Framework\TestCase;

class GeoMathTest extends TestCase
{
    public function test_point_in_polygon(): void
    {
        $square = [[0, 0], [0, 10], [10, 10], [10, 0]];

        $this->assertTrue(GeoMath::pointInPolygon(5, 5, $square));
        $this->assertFalse(GeoMath::pointInPolygon(15, 5, $square));
        $this->assertFalse(GeoMath::pointInPolygon(-1, -1, $square));
    }

    public function test_concave_polygon(): void
    {
        // "U" şekli: ortadaki boşluk dışarıda sayılmalı
        $u = [[0, 0], [0, 10], [10, 10], [10, 7], [3, 7], [3, 3], [10, 3], [10, 0]];

        $this->assertTrue(GeoMath::pointInPolygon(1, 5, $u));
        $this->assertFalse(GeoMath::pointInPolygon(6, 5, $u));
    }

    public function test_haversine_distance(): void
    {
        // Antalya Havalimanı → Kaleiçi ≈ 11 km kuş uçuşu
        $km = GeoMath::haversineKm(36.8987, 30.8005, 36.8841, 30.7056);
        $this->assertEqualsWithDelta(8.6, $km, 0.5);
    }
}
