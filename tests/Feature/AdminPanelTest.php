<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Models\User;
use App\Services\Reservations\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

/** Panelin tüm sayfaları örnek veriyle hatasız render ediliyor mu? */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    public static function pages(): array
    {
        return array_map(fn ($p) => [$p], [
            '', '/transfers', '/transfers?tab=unassigned', '/reservations', '/reservations/create', '/reservations/1',
            '/reservations/1/edit', '/quote-calculator', '/zone-map', '/zones', '/zones/1/edit', '/locations',
            '/locations/create', '/routes', '/routes/1/edit', '/pricing-rules', '/extras', '/vehicle-types',
            '/drivers', '/vehicles', '/agencies',
        ]);
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $path): void
    {
        $this->seedWorld();
        Mail::fake();
        $this->makeDriver();
        $this->makeAgency();
        app(ReservationService::class)->create($this->reservationPayload(), BookingSource::Admin);

        $this->actingAs(User::factory()->create())
            ->get('/admin'.$path)
            ->assertOk();
    }
}
